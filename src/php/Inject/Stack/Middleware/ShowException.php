<?php
/*
 * Created by Martin Wernståhl on 2011-04-29.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \SimpleXMLElement;

use \Inject\Stack\Request;
use \Inject\Stack\MiddlewareInterface;

/**
 * Takes any thrown exception and prints the exception and stack trace,
 * supports html, json and xml depending on Accept header.
 */
class ShowException implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	// ------------------------------------------------------------------------
	
	/**
	 * Tells this middleware which middleware or endpoint it should call if it
	 * wants the call-chain to proceed.
	 * 
	 * @param  \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	public function setNext($next)
	{
		$this->next = $next;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Performs the operations of the middleware.
	 * 
	 * @param  array
	 * @return array(int, array(string => string), string)
	 */
	public function __invoke($env)
	{
		try
		{
			$callback = $this->next;
			return $callback($env);
		}
		catch(\Exception $e)
		{
			return array(500, array(), $this->renderException($env, $e));
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Renders the exception.
	 * 
	 * @param  array
	 * @param  \Exception
	 * @return string
	 */
	public function renderException($env, $exception)
	{
		$req = new Request($env);
		
		$type = $req->negotiateMime(array('text/html', 'application/json', 'application/xml'));
		
		$data = array(
			'type'    => get_class($exception),
			'message' => $exception->getMessage(),
			'code'    => $exception->getCode(),
			'trace'   => array_merge(array(array(
					'file'     => $exception->getFile(),
					'line'     => $exception->getLine(),
					'args'     => array(),
					'function' => 'throw'
				)),
				$exception->getTrace())
		);
		
		switch($type)
		{
			case 'application/json':
				
				$data = array(
					'exception' => $data
				);
				
				return json_encode($data);
				
			case 'application/xml':
				
				return $this->XMLencode($data);
				
			default:
				ob_start();
				
				include __DIR__.'/ShowException.html.php';
				
				return ob_get_clean();
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Converts a supplied array (with non-numeric keys in the first level) to
	 * an XML string.
	 * 
	 * @param  array
	 * @return string
	 */
	public function XMLencode($data)
	{
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><exception></exception>');
		
		// Function dealing with the array->XML conversion
		$fun = function($xml, $data) use(&$fun)
		{
			foreach($data as $key => $value)
			{
				if(is_array($value))
				{
					if(isset($value[0]))
					{
						// Numeric indices so we need to repeat the $key tag
						foreach($value as $entry)
						{
							$node = $xml->addChild($key);
							
							$fun($node, $entry);
						}
					}
					else
					{
						$node = $xml->addChild($key);
						
						$fun($node, $value);
					}
				}
				else
				{
					$xml->addChild($key, $value);
				}
			}
		};
		
		$fun($xml, $data);
		
		return $xml->asXML();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Extracts the code around the specified line in the specified file, 
	 * wraps it in a table, adds line numbers and highlights it.
	 * 
	 * @param  string
	 * @param  int
	 * @return string
	 */
	protected function extractCode($file, $line)
	{
		$data = explode("\n", file_get_contents($file));
		
		$code = array_slice($data, max($line - 4, 0), 7, true);
		
		$self = $this;
		
		$code = array_map(function($k, $l) use($line, $self)
		{
			$k++;
			return '<tr class="line'.($k == $line ? ' current' : '').'"><td class="line_no">'.($k + 1).'</td><td>'.$self->highlight($l, true).'</td></tr>';
		}, array_keys($code), array_values($code));
		
		return '<table cellpadding="0" cellspacing="0" border="0">'.implode("\n", $code).'</table>';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Simple PHP Code highlighter using the Tokenizer extension to tokenize
	 * the code.
	 * 
	 * @param  string  PHP code, without leading <?php
	 * @return string  HTML string with the code
	 */
	public function highlight($string)
	{
		$tokens = @token_get_all('<?php '.$string);
		
		$result = array();
		
		foreach($tokens as $tok)
		{
			if( ! is_array($tok))
			{
				$result[] = $tok;
				continue;
			}
			switch($tok[0])
			{
				// Keywords
				case T_ABSTRACT:
				case T_ARRAY:
				case T_AS:
				case T_BREAK:
				case T_CASE:
				case T_CATCH:
				case T_CLASS:
				case T_CLONE:
				case T_CONST:
				case T_CONTINUE:
				case T_DECLARE:
				case T_DEFAULT:
				case T_DO:
				case T_ELSE:
				case T_ELSEIF:
				case T_ENDDECLARE:
				case T_ENDFOR:
				case T_ENDFOREACH:
				case T_ENDIF:
				case T_ENDSWITCH:
				case T_ENDWHILE:
				case T_END_HEREDOC:
				case T_EXIT:
				case T_EXTENDS:
				case T_FINAL:
				case T_FOR:
				case T_FOREACH:
				case T_FUNCTION:
				case T_GLOBAL:
				case T_GOTO:
				case T_IF:
				case T_IMPLEMENTS:
				case T_INTERFACE:
				case T_NAMESPACE:
				case T_NEW:
				case T_PRIVATE:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_STATIC:
				case T_SWITCH:
				case T_THROW:
				case T_TRY:
				case T_USE:
				case T_VAR:
				case T_WHILE:
					$result[] = '<span style="color: #766ffa">'.$tok[1].'</span>';
					break;
				
				// Function
				case T_ECHO:
				case T_EMPTY:
				case T_EVAL:
				case T_HALT_COMPILER:
				case T_INCLUDE:
				case T_INCLUDE_ONCE:
				case T_ISSET:
				case T_LIST:
				case T_PRINT:
				case T_REQUIRE:
				case T_REQUIRE_ONCE:
				case T_RETURN:
				case T_UNSET:
				case T_STRING:
					$result[] = '<span style="color: #ac31ae">'.$tok[1].'</span>';
					break;
				
				// Operators
				case T_AND_EQUAL:
				case T_ARRAY_CAST:
				case T_BOOLEAN_AND:
				case T_BOOLEAN_OR:
				case T_BOOL_CAST:
				case T_CONCAT_EQUAL:
				case T_DEC:
				case T_DIV_EQUAL:
				case T_DOUBLE_ARROW:
				case T_DOUBLE_CAST:
				case T_DOUBLE_COLON:
				case T_INC:
				case T_INSTANCEOF:
				case T_INT_CAST:
				case T_IS_EQUAL:
				case T_IS_GREATER_OR_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
				case T_IS_SMALLER_OR_EQUAL:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_MINUS_EQUAL:
				case T_MOD_EQUAL:
				case T_MUL_EQUAL:
				case T_OBJECT_CAST:
				case T_OBJECT_OPERATOR:
				case T_OR_EQUAL:
				case T_PAAMAYIM_NEKUDOTAYIM:
				case T_PLUS_EQUAL:
				case T_SL:
				case T_SL_EQUAL:
				case T_SR:
				case T_SR_EQUAL:
				case T_START_HEREDOC:
				case T_STRING_CAST:
				case T_UNSET_CAST:
				case T_XOR_EQUAL:
					$result[] = '<span style="color: #b5b4cf">'.$tok[1].'</span>';
					break;
				
				// Constants
				case T_CLASS_C:
				case T_DIR:
				case T_FILE:
				case T_FUNC_C:
				case T_METHOD_C:
					$result[] = '<span style="color: #6b63ab">'.$tok[1].'</span>';
					break;
				
				// String
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_NUM_STRING:
				case T_STRING_VARNAME:
					$result[] = '<span style="color: #9868ab">'.$tok[1].'</span>';
					break;
				
				// Comments
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_LINE:
				case T_NS_C:
					$result[] = '<span style="color: #c486db">'.$tok[1].'</span>';
					break;
				 
				// Whitespace
				case T_WHITESPACE:
				case T_NS_SEPARATOR:
					$result[] = strtr(htmlentities($tok[1]), array(' ' => '&nbsp;', "\t" => '&nbsp;&nbsp;&nbsp;&nbsp;'));
					break;
				
				// Variable
				case T_VARIABLE:
					$result[] = '<span style="color: #6ca373">'.$tok[1].'</span>';
					break;
					
				// Number
				case T_DNUMBER:
				case T_LNUMBER:
					$result[] = '<span style="color: #ac31ae">'.$tok[1].'</span>';
					break;
				
				// Skip
				case T_CLOSE_TAG:
				case T_INLINE_HTML:
				case T_OPEN_TAG:
				case T_OPEN_TAG_WITH_ECHO:
				
			}
		}
		
		return implode('', $result);
	}
}


/* End of file Failsafe.php */
/* Location: src/php/Inject/Stack/Middleware */