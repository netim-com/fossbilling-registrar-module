<?php
/** 
@created 15/11/17
@lastUpdated 30/10/20
@version 1.0.0-BETA

Utilities to deal with  the normalization of country, phone, ... 
*/

namespace Netim
{
	class Normalization
	{
		public function __construct()
		{
		}
		
		public function stripAccent($str)
		{
			$patterns = array('/à/','/á/','/â/','/ã/','/ä/','/å/','/ā/','/ă/','/ą/','/æ/','/ç/','/ć/','/ĉ/','/ċ/','/č/','/ď/','/đ/','/è/','/é/','/ê/','/ë/','/ē/','/ĕ/','/ė/','/ę/','/ě/','/ĝ/','/ġ/','/ģ/','/ĥ/','/ħ/','/ì/','/í/','/î/','/ï/','/ĩ/','/ī/','/ĭ/','/į/','/ı/','/ĵ/','/ķ/','/ĺ/','/ļ/','/ľ/','/ŀ/','/ł/','/ñ/','/ń/','/ņ/','/ň/','/ŉ/','/ò/','/ó/','/ô/','/õ/','/ö/','/ō/','/ŏ/','/ő/','/ø/','/ð/','/œ/','/ŕ/','/ŗ/','/ř/','/ś/','/ŝ/','/š/','/ș/','/ť/','/ŧ/','/ț/','/ù/','/ú/','/û/','/ü/','/ũ/','/ū/','/ŭ/','/ů/','/ű/','/ų/','/ŵ/','/ý/','/ÿ/','/ŷ/','/ź/','/ż/','/ž/','/ß/','/À/','/Á/','/Â/','/Ã/','/Ä/','/Å/','/Ā/','/Ă/','/Ą/','/Æ/','/Ç/','/Ć/','/Ĉ/','/Ċ/','/Č/','/Ď/','/Đ/','/È/','/É/','/Ê/','/Ë/','/Ē/','/Ĕ/','/Ė/','/Ę/','/Ě/','/Ĝ/','/Ġ/','/Ģ/','/Ĥ/','/Ħ/','/İ/','/Ì/','/Í/','/Î/','/Ï/','/Ĩ/','/Ī/','/Ĭ/','/Į/','/I/','/Ĵ/','/Ķ/','/Ĺ/','/Ļ/','/Ľ/','/Ŀ/','/Ł/','/Ñ/','/Ń/','/Ņ/','/Ň/','/Ò/','/Ó/','/Ô/','/Õ/','/Ö/','/Ō/','/Ŏ/','/Ő/','/Ø/','/Œ/','/Ŕ/','/Ŗ/','/Ř/','/Ś/','/Ŝ/','/Š/','/Ș/','/Ť/','/Ŧ/','/Ț/','/Ù/','/Ú/','/Û/','/Ü/','/Ũ/','/Ū/','/Ŭ/','/Ů/','/Ű/','/Ų/','/Ŵ/','/Ý/','/Ÿ/','/Ŷ/','/Ź/','/Ż/','/Ž/');

			$replacements = array('a','a','a','a','a','a','a','a','a','ae','c','c','c','c','c','d','d','e','e','e','e','e','e','e','e','e','g','g','g','h','h','i','i','i','i','i','i','i','i','i','j','k','l','l','l','l','l','n','n','n','n','n','o','o','o','o','o','o','o','o','o','o','oe','r','r','r','s','s','s','s','t','t','t','u','u','u','u','u','u','u','u','u','u','w','y','y','y','z','z','z','ss','A','A','A','A','A','A','A','A','A','AE','C','C','C','C','C','C','D','E','E','E','E','E','E','E','E','E','G','G','G','G','H','I','I','I','I','I','I','I','I','I','I','J','K','L','L','L','L','L','N','N','N','N','O','O','O','O','O','O','O','O','O','OE','R','R','R','S','S','S','S','T','T','T','U','U','U','U','U','U','U','U','U','U','W','Y','Y','Y','Z','Z','Z');
			
			return preg_replace($patterns,$replacements,$str);
		}
		
		public function stripSpecialChar($str)
		{
			$patterns = array('/-/', '/_/', '/,/');
			
			$replacements = array(' ', ' ', '');
			
			$res = preg_replace($patterns,$replacements,$str);
			return $res;
		}
		
		/**
		* Strip accent, special char and make the $str lowercase
		*
		* @param $str string
		*
		* @return string 
		**/
		public function process($str)
		{
			$res = $this->stripAccent($str);
			$res = $this->stripSpecialChar($res);
			$res = strtolower($res);
			return $res;
		}
		
		/**
		* Removes all null values from an array
		*
		* @param $arr array An array
		* @return array An array with all the key/value of $arr removing null values
		*
		* @timecomplexity O(n)
		*/
		public function array_removeNull($arr)
		{
			return array_filter($arr, function($var){return !is_null($var);});
		}
		
		
		/**
		* Takes a pattern and adds delimiter, anchors and options if needed to make it a valid regex
		* 
		* @param $wanabeRegex string A string to be used as a regex pattern
		* @param $delimiter char OPTIONAL The delimiter to be used in the regex, default is '/'
		* @param $isStartOfLine bool OPTIONAL If true, adds '^' anchor at the start of regex. Default is true
		* @param $isEndOfLine bool OPTIONAL If true, adds '$' anchor at the end of regex. Default is true
		* @param $options string OPTIONAL A string representing options to use for the regex as defined for PCRE. Default is 'i' (case insensitive regex)
		*
		* @return string 
		*/
		//TODO trouver un meilleur nom pour fn et param
		private function prepareRegex($wannabeRegex, $delimiter='/', $isStartOfLine = true, $isEndOfLine = true, $options = 'i')
		{
			$res = $delimiter;
			$res .= $isStartOfLine ? '^' : '';
			$res .= '('.$wannabeRegex.')';
			$res .= $isEndOfLine ? '$' : '';
			$res .= $delimiter . $options;
			return $res;
		}
		
		/**
		* Function that can be used in constructMatch as the callable parameter.
		*
		* @param $text string A string to be search in by a regex
		*
		* @return A closure that takes two parameter $key and $val, and use $text as a out-of-scope variable.
		*         The closure do a regex match between $val and $text, and return $key when there is a match.
		*
		* @see constructMatch
		*/
		//closure
		private function testMatch($text)
		{
			return function ($key, $val) use($text)
					{
						if(preg_match($this->prepareRegex($val), $text))
						return $key;
					};
		}
		
		/**
		* Map $fn onto $data keys/values, and $fn takes an additional argument $text
		*
		* @param $data array An array to be processed
		* @param $arg mixed An arg to be given to $fn
		* @param $fn callable A function that takes a parameter, and return a function that takes 2 parameters
		*
		* @return array An arra containing the result of the mapping of $fn($arg) onto $data
		*
		* @timecomplexity O(n) * complexity of $fn($arg)
		*/
		//TODO find better names
		public function constructMatch($data, $arg, $fn=null)
		{
			if($fn == null)
				$fn = array($this, 'testMatch');
			$res = array_map($fn($arg), array_keys($data), $data);
			return $res;
		}
		
		
		/**
		* Normalize a string to get conform phone number for api
		*
		* @see whmcs module, netim.php
		*/ 
		public function phoneNumber($str, $country)
		{
			if(empty($str))
				return $str;
			
			require (__DIR__ . DIRECTORY_SEPARATOR . 'constNormalization.inc.php');
			
			// $str is normalized as "+countrycode digits" to conform API values	 
			$country=strtoupper($country);
			$str=str_replace(" ","",$str);
			$str=str_replace("-","",$str);
			$str=str_replace(".","",$str);
			$str=str_replace("(","",$str);
			$str=str_replace(")","",$str);
			
			if(preg_match("#^\+#",$str))
			{
				if(preg_match("#^\+".$countryPhoneCode[$country]."#",$str))
				{
					//Number has the international syntax for the country
					$new_str="+".$countryPhoneCode[$country]." ".substr($str,3,strlen($str));
				}
				else
				{
					//Number has the international syntax for another country but we are unable to extract the country code 
					$new_str=substr($str,0,3)." ".substr($str,3,strlen($str));
				}
			}
			else if(preg_match("#^00#",$str))
			{
				if(preg_match("#^00".$countryPhoneCode[$country]."#",$str))
				{
					//Number has the international syntax for the country
					$new_str="+".$countryPhoneCode[$country]." ".substr($str,2+strlen($countryPhoneCode[$country]),strlen($str));
				}
				else
				{
					//Number has the international syntax for another country but we are unable to extract the country code 
					$new_str="+".substr($str,2,2)." ".substr($str,4,strlen($str));
				}
			}
			else
			{
				//Number has not the international syntax
				$new_str="+".$countryPhoneCode[$country]." ".substr($str,1,strlen($str));
			}
			
			return $new_str;
		}
		
		/**
		* Normalize a string to get conform state for api
		*
		* @see whmcs module, netim.php
		*/ 
		public function state($str ,$country)
		{
			require (__DIR__ . DIRECTORY_SEPARATOR . 'constNormalization.inc.php');
			$country=$this->country($country);
			$str=$this->specialCharacter( $str ); // Removal of accentued characters
			if(isset($stateCodeToRegex[$country]))
			{
				foreach($stateCodeToRegex[$country] as $key => $value)
				{
					$t=explode("|",$value);
					foreach($t as $value2)
					{
						if(strtolower($value2)==strtolower($str))
							return $key;
					}
				}
				
				//the value is not found, we use the first key of the array
				return array_keys($stateCodeToRegex[$country])[0];
			}

			return "";
		}

		/**
		* Normalize a string to get conform state for api
		*
		* @see whmcs module, netim.php
		*/ 
		public function country($country)
		{
			require (__DIR__ . DIRECTORY_SEPARATOR . 'constNormalization.inc.php');

			foreach($countryCodeToRegex as $key => $value)
			{
				$t=explode("|",$value);
				foreach($t as $value2)
				{
					if(strtolower($value2)==strtolower($country))
						return $key;
				}
			}
			
			//the value is not found, we use the first key of the array
			return array_keys($countryCodeToRegex)[0];
		}

		/**
		* Normalize a string to remove all special char 
		*
		* @see whmcs module, netim.php
		*/ 
		public function specialCharacter($str)
		{
			// $str is normalized. All special characters are replaced to conform API
			//$str=mb_strtolower($str,'UTF-8');

			$patterns = array();
			$replacements = array();
			
			# Quotes cleanup
			$patterns[0] = "/".chr(ord("`"))."/";
			$replacements[0] = "'";
			$patterns[1] = "/".chr(ord("´"))."/";
			$replacements[1] = "'";
			$patterns[2] = "/".chr(ord("„"))."/";
			$replacements[2] = " ";
			$patterns[3] = "/".chr(ord("“"))."/";
			$replacements[3] = "\"";
			$patterns[4] = "/".chr(ord("”"))."/";
			$replacements[4] = "\"";
			
			# Bullets, dashes, and trademarks
			$patterns[5] = "/".chr(149)."/";
			$replacements[5] = "&#8226;";
			$patterns[6] = "/".chr(150)."/";
			$replacements[6] = "&ndash;";
			$patterns[7] = "/".chr(151)."/";
			$replacements[7] = "&mdash;";
			$patterns[8] = "/".chr(153)."/";
			$replacements[8] = "&#8482;";
			$patterns[9] = "/".chr(169)."/";
			$replacements[9] = "&copy;";
			$patterns[10] = "/".chr(174)."/";
			$replacements[10] = "&reg;";
		
			$str = preg_replace($patterns, $replacements, $str);
			
			return $str;
		}
	}
}