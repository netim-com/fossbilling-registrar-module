<?php

/** 
 * @created 03/10/17
 * @lastUpdated 31/08/21
 * @version 2.0.0
 *  
 * Generic class for a client API. Handle the SOAP connection to NETIM's API, and many operation described here: http://support.netim.com/en/wiki/Category:Functions
 * 
 * How to use the class?
 * =====================
 * 
 * Beforehand you need to include this script into your php script:
 * ```php
 * 		include_once('$PATH/APISoap.php');
 * 		//(replace $PATH by the path of the file)
 * ```
 * 
 * Then you can instantiate a APISoap object:
 * ```php
 * 		$username = 'yourUsername';
 * 		$secret = 'yourSecret';
 * 		$client = new APISoap($username, $secret);
 * ```
 * 
 * You can also create a conf.xml file next to the APISoap.php class with the login credentials to connect to the API with no parameters
 * 	
 * Now that you have your object, you can issue commands to the API.
 * 
 * Say you want to see the information you gave when creating your contact, and your contact ID is 'GK521'.
 * The code is:
 * ```php
 * 		$result = $client->contactInfo('GK521');
 * ```
 * 
 * (SIDENOTE: you may have noticed that you didn't need to explicitely open nor close a connexion with the API, the client handle it for you.
 * It is good for shortlived scripts. The connection is automatically stopped when the script ends. However if you open multiple connections
 * in a long running script, you should close each connection when you don't need them anymore to avoid having too many connections opened).
 * 
 * To know if there is an error we provide you an exception type NetimAPIException
 * 
 * How to issue many commands more effectively
 * ===========================================
 * 
 * Previously we saw how to issue a simple command. Now we will look into issueing many commands sequentially.
 * 
 * Let's take an example, we want to create 2 contacts, look up info on 2 domains and look up infos on the contacts previously created
 * We could do it simply:
 * ```php
 * 		//creating contacts
 * 		try
 * 		{
 * 			$result1 = $client->contactCreate(...); //skipping needed parameters here for the sake of the example brevity
 * 			$result2 = $client->contactCreate(...);
 * 			
 * 			//asking for domain informations
 * 			$result3 = $client->domainInfo('myDomain.fr');
 * 			$result4 = $client->domainInfo('myDomain.com');
 * 		}
 * 		catch (NetimAPIException $exception)
 * 		{
 * 			//do something about the error
 * 		}
 * 		
 * 		//asking for contact informations
 * 		$result5 = $client->contactInfo($result1));
 * 		$result6 = $client->contactInfo($result2));
 * ```
 * 	
 * The connection is automatically closed when the script ends. However we recommend you to close the connection yourself when you won't use it
 * anymore like so : 
 * ```php
 * 		$client->sessionClose();
 * ```
 * The reason is that PHP calls the destructor only if it's running out of memory or when the script ends. If your script is running in a cron for
 * example, and it instanciates many APISoap objects without closing them, you may reach the limit of sessions you're allowed to open.
 */

namespace Netim {

    use SoapFault;
	use SoapClient;
    use stdClass;

	ini_set("soap.wsdl_cache_enabled", "0");

	abstract class AbstractAPISoap
	{

		private $_connected;
		private $_sessionID;
		private $_clientSOAP;

		private $_userID;
		private $_password;
		private $_apiURL;
        private $_defaultLanguage;

		private $_lastRequestParams;
		private $_lastRequestFunction;
		private $_lastResponse;
		private $_lastError;

		/**
		 * Constructor for class AbstractAPISoap
		 *
		 * @param string $userID the ID the client uses to connect to his NETIM account
		 * @param string $password the PASSWORD the client uses to connect to his NETIM account
		 *	 
		 * @throws Error if $userID, $password or $apiURL are not string or are empty
		 * 
		 * @link semantic versionning http://semver.org/ by Tom Preston-Werner 
		 */
		protected function __construct(string $userID, string $password, string $apiURL, string $defaultLanguage)
		{
            register_shutdown_function([&$this, "__destruct"]);
			// Init variables
			$this->_connected = false;
			$this->_sessionID = null;

            $this->_userID = $userID;
            $this->_password = $password;
            $this->_apiURL = $apiURL;
            $this->_defaultLanguage = $defaultLanguage;

			// Init Client Soap object
			$this->_clientSOAP = new SoapClient($this->_apiURL);
		}

		public function __destruct()
		{
			if ($this->_connected && isset($this->_sessionID))
            	$this->sessionClose();

		}

		public function getLastRequestParams()
		{
			return $this->_lastRequestParams;
		}
		public function getLastRequestFunction()
		{
			return $this->_lastRequestFunction;
		}
		public function getLastResponse()
		{
			return $this->_lastResponse;
		}
		public function getLastError()
		{
			return $this->_lastError;
		}
		public function getUserID()
		{
			return $this->_userID;
		}
		public function getUserPassword()
		{
			return $this->_password;
		}
		# ---------------------------------------------------
		# PRIVATE UTILITIES
		# ---------------------------------------------------
		
		/**
		 * Launches a function of the API, abstracting the connect/disconnect part to one place
		 *
		 * Example 1: API command returning a StructOperationResponse
		 *
		 *	$params[] = $idContactToDelete;
		 *	return $this->_launchCommand('contactDelete', $params);
		 *
		 * Example 2: API command that takes many args
		 *
		 *	$params[] = $host;
		 *	$params[] = $ipv4;
		 *	$params[] = $ipv6;
		 *	return $this->_launchCommand('hostCreate', $params);
		 *
		 * WARNING: as in the example 2 just above, the second parameter you give to _launchCommand must be an indexed array with parameters in the right order relative to the parameter the API function takes. 
		 *          Example: the function hostCreate of the API takes exactly 3 parameters in that exact order:  $host, $ipv4, $ipv6, see example 2.
		 *
		 * @param string $fn name of a function in the API
		 * @param array $params the parameters of $fn in an indexed array, must be in the right order.
		 *
		 * @throws NetimAPIException
		 *
		 * @return mixed the result of the call of $fn with parameters $params
		 *
		 * @see call_user_func_array https://stackoverflow.com/questions/1422652/how-to-pass-variable-number-of-arguments-to-a-php-function
		 *                           http://php.net/manual/fr/function.call-user-func-array.php
		 * @see array_unshift http://php.net/manual/en/function.array-unshift.php
		 */
		protected function _launchCommand($fn, $params=array())
		{
			if($fn != "sessionOpen" && $fn != "login")
			{
				// Don't replace info if the _launchCommand needs to ope an session
				$this->_lastRequestFunction = $fn;
				$this->_lastRequestParams = $params;
			}
			$this->_lastResponse = "";
			$this->_lastError = "";

			try {
				//login		
				if (!$this->_connected)
                {
                    if ($fn == "sessionClose") //If already disconnected, just return.
                        return;
                    else if ($fn != "sessionOpen" && $fn != "login") // If not connected and running sessionOpen, don't fall in an endless loop.
                        $this->sessionOpen();
                }
                else if($this->_connected && $fn == "sessionOpen")
                    return;

				//Call the Soap function
                if($fn != "sessionOpen" && $fn != "login")
				    array_unshift($params, $this->_sessionID);                
				$res = call_user_func_array(array($this->_clientSOAP, $fn), $params);
				$this->_lastResponse = $res;
			} catch (NetimAPIException $exception) {
				$this->_lastError = $exception->getMessage();
				throw new NetimAPIexception($exception->getMessage(), $exception->getCode(), $exception);
			} catch (SoapFault $fault) {
				if (!($fn == "sessionClose" && str_contains($fault->getMessage(), "E02")))
				{
					$this->_lastError = $fault->getMessage();
					throw new NetimAPIexception($fault->getMessage(), $fault->getCode(), $fault);
				}
				else
					return;
			}
            
            if ($fn == "sessionClose")
            {
                $this->_connected = false;
            }
            else if($fn == "sessionOpen" || $fn == "login")
            {
                $this->_sessionID = $res;
                $this->_connected = true;
            }

			return $res;
		}

		# -------------------------------------------------
		# MISC
		# -------------------------------------------------	
		/**
		 * Returns a welcome message
		 *
		 * Example
		 *	```php
		 *	try
		 *	{
		 *		$res = $client->hello();
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @return string a welcome message
		 * 
		 * @throws NetimAPIException
		 *
		 * @see hello API http://support.netim.com/en/wiki/Hello
		 */
		public function hello():string
		{
			return $this->_launchCommand('hello');
		}
        
		/**
		 * Returns the list of parameters reseller account
		 *
		 *
		 * @return StructQueryResellerAccount A structure of StructQueryResellerAccount containing the information
		 * 
		 * @throws NetimAPIException
		 *
		 * @see queryResellerAccount API https://support.netim.com/en/wiki/QueryResellerAccount
		 */
        public function queryResellerAccount():stdClass
		{
			return $this->_launchCommand('queryResellerAccount');
		}
        
		public function queryOpe(int $operationID):stdClass
		{
			$params[] = $operationID;
			return $this->_launchCommand('queryOpe', $params);
		}

        # -------------------------------------------------
		# SESSION
		# -------------------------------------------------	 

		/**
		 * Open the SOAP session
		 *
		 * @throws NetimAPIException
		 */
		public function sessionOpen():void
		{        
            $params[] = strtoupper($this->_userID);
            $params[] = $this->_password;
            $params[] = $this->_defaultLanguage;
           	$this->_launchCommand('sessionOpen', $params);
		}

		/**
		 * Close the SOAP session
		 * 
		 * @throws NetimAPIException
		 */
		public function sessionClose():void
		{
			$this->_launchCommand('sessionClose');
		}
        
		/**
		 * Return the information of the current session. 
		 *
		 * @throws NetimAPIException
		 * 
		 * @return StructSessionInfo A structure StructSessionInfo
		 *
		 * @see sessionInfo API https://support.netim.com/en/wiki/SessionInfo
		 */
        public function sessionInfo():stdClass
        {
			return $this->_launchCommand('sessionInfo');
        }
        
		/**
		 * Returns all active sessions linked to the reseller account. 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructSessionInfo[] An array of StructSessionInfo
		 *
		 * @see queryAllSessions API https://support.netim.com/en/wiki/QueryAllSessions
		 */
        public function queryAllSessions():array
		{
			return $this->_launchCommand('queryAllSessions');
		}
        
        /**
		 * Updates the settings of the current session. 
		 *
		 * @param string $type Setting to be modified : lang
		 *                                              sync
		 * @param string $value New value of the Setting : lang = EN / FR
		 *                                                 sync = 0 (for asynchronous) / 1 (for synchronous) 
		 * @throws NetimAPIException
		 *
		 * @see sessionSetPreference API https://support.netim.com/en/wiki/SessionSetPreference
		 */
        public function sessionSetPreference(string $type, string $value):void
        {
			$params = [];
			$params[] = $type;
			$params[] = $value;
			$this->_launchCommand('sessionSetPreference', $params);
        }
        
		# -------------------------------------------------
		# OPERATIONS
		# -------------------------------------------------	 

        /**
		 * Cancel a pending operation
		 * @warning Depending on the current status of the operation, the cancellation might not be possible
		 * 
		 * @param int $operationID Tracking ID of the operation
		 * 
		 * @throws NetimAPIException
		 *
		 * @see cancelOpe http://support.netim.com/en/wiki/CancelOpe
		 */
		//TODO voir si le fait que cancel ne retourne rien ne fait pas d'erreur
		public function cancelOpe(int $operationID):void
		{
			$params[] = $operationID;
			$this->_launchCommand('cancelOpe', $params);
		}
        
        /**
		 * Returns the status (opened/closed) for all operations for the extension 
		 * 
		 * @param string $tld Extension (uppercase without dot)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return object An associative array with (Name of the operation, boolean active)
		 * 
		 * @see queryOpeList API https://support.netim.com/en/wiki/QueryOpeList
		 * 
		 */
		public function queryOpeList(string $tld):stdClass
		{
			$params[] = $tld;
			return $this->_launchCommand('queryOpeList', $params);
		}
        
		/**
		 * Returns the list of pending operations processing 
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructQueryOpePending[]  the list of pending operations processing 
		 * 
		 * @see queryOpePending API https://support.netim.com/en/wiki/QueryOpePending
		 * 
		 */
        public function queryOpePending():array
		{
			return $this->_launchCommand('queryOpePending');
		}
        
        /**
		 * Returns the list of pending operations processing 
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructContactList[] the list of contacts associated to the account
		 * 
		 * @see queryContactList API https://support.netim.com/en/wiki/QueryContactList
		 * 
		 */
        public function queryContactList(string $filter="", string $field=""):array
		{
			$params[] = $filter;
			$params[] = $field;
			return $this->_launchCommand('queryContactList', $params);
		}
        
        # -------------------------------------------------
		# HOST
		# -------------------------------------------------
		/**
		 * Creates a new host at the registry
		 *
		 * Example
		 *	```php
		 *	$host = 'ns1.mydomain.com';
		 *	$ipv4 = array('10.11.12.13');
		 *	$ipv6 = array();
		 *	$res = null;
		 *	try
		 *	{
		 *		$res =  $client->hostCreate($host, $ipv4, $ipv6);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $host hostname
		 * @param array $ipv4 Must contain ipv4 adresses as strings
		 * @param array $ipv6 Must contain ipv6 adresses as strings
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see hostCreate API http://support.netim.com/en/wiki/HostCreate
		 */
		public function hostCreate(string $host, array $ipv4, array $ipv6):stdClass
		{
			$params[] = $host;
			$params[] = $ipv4;
			$params[] = $ipv6;
			return $this->_launchCommand('hostCreate', $params);
		}

		/**
		 * Deletes an Host at the registry 
		 *
		 * Example
		 *	```php
		 *	$host = 'ns1.mydomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->hostDelete($host);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $host hostname to be deleted
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see hostDelete API http://support.netim.com/en/wiki/HostDelete
		 */
		public function hostDelete($host):stdClass
		{
			$params[] = $host;
			return $this->_launchCommand('hostDelete', $params);
		}
        
        /**
		 * Updates a host at the registry 
		 *
		 * Example
		 *	```php
		 *	$host = 'ns1.myDomain.com';
		 *	$ipv4 = array('10.12.13.11');
		 *	$ipv6 = array();
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->hostUpdate($host, $ipv4, $ipv6);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $host string hostname
		 * @param array $ipv4 Must contain ipv4 adresses as strings
		 * @param array $ipv6 Must contain ipv6 adresses as strings
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see hostUpdate API http://support.netim.com/en/wiki/HostUpdate
		 */
		public function hostUpdate(string $host, array $ipv4, array $ipv6):stdClass
		{
			$params[] = $host;
			$params[] = $ipv4;
			$params[] = $ipv6;
			return $this->_launchCommand('hostUpdate', $params);
		}
        
        /**
		 * @param string $filter The filter applies onto the host name 
		 * 
		 * @throws NetimAPIException
		 *
		 * @return array An array of StructHostList
		 *
		 * @see queryHostList API http://support.netim.com/en/wiki/QueryHostList
		 */
		public function queryHostList(string $filter):array
		{
			$params[] = $filter;
			return $this->_launchCommand('queryHostList', $params);
		}
        
		# -------------------------------------------------
		# CONTACT
		# -------------------------------------------------	        

		/**
		 * Creates a contact
		 *
		 * Example1: non-owner
		 *	```php
		 *	//we create a contact as a non-owner 
		 *	$id = null;
		 *	try
		 *	{
		 *		$contact = array(
		 *	 		'firstName'=> 'barack',
		 *			'lastName' => 'obama',
		 *			'bodyForm' => 'IND',
		 *			'bodyName' => '',
		 *			'address1' => '1600 Pennsylvania Ave NW',
		 *			'address2' => '',
		 *			'zipCode'  => '20500',
		 *			'area'	   => 'DC',
		 *			'city'	   => 'Washington',
		 *			'country'  => 'US',
		 *			'phone'	   => '2024561111',
		 *			'fax'	   => '',
		 *			'email'    => 'barack.obama@gov.us',
		 *			'language' => 'EN',
		 *			'isOwner'  => 0
		 *		);
		 *		$id = $client->contactCreate($contact);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *
		 *	//continue processing
		 *	```
		 *
		 * Example2: owner
		 *	```php	
		 *	$id = null;
		 *	try
		 *	{
		 *	 	$contact = array(
		 *	 		'firstName'=> 'bill',
		 *			'lastName' => 'gates',
		 *			'bodyForm' => 'IND',
		 *			'bodyName' => '',
		 *			'address1' => '1 hollywood bvd',
		 *			'address2' => '',
		 *			'zipCode'  => '18022',
		 *			'area'	   => 'LA',
		 *			'city'	   => 'Los Angeles',
		 *			'country'  => 'US',
		 *			'phone'	   => '2024531111',
		 *			'fax'	   => '',
		 *			'email'    => 'bill.gates@microsoft.com',
		 *			'language' => 'EN',
		 *			'isOwner'  => 1
		 *		);
		 *		$id = $client->contactCreate($contact);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *
		 *	//continue processing
		 *	```
		 * @param StructContact $contact the contact to create
		 *
		 * @throws NetimAPIException
		 *
		 * @return string the ID of the contact
		 *
		 * @see StructContact http://support.netim.com/en/wiki/StructContact
		 */
		public function contactCreate(array $contact): string
		{
			$params[] = $contact;
			return $this->_launchCommand('contactCreate', $params);
		}

		/**
		 * Returns all informations about a contact object
		 *
		 * Example:
		 *	```php
		 *	$idContact = 'BJ007';
		 *	$res = null;
		 *	try 
		 *	{
		 *		$res = $client->contactInfo($idContact);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *	$contactInfo = $res;
		 *	//continue processing
		 *	```
		 * @param string $idContact ID of the contact to be queried
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructContactReturn information on the contact
		 *
		 * @see contactInfo API http://support.netim.com/en/wiki/ContactInfo
		 * @see StructContactReturn API http://support.netim.com/en/wiki/StructContactReturn
		 */
		public function contactInfo(string $idContact): stdClass
		{
			$params[] = $idContact;
			return $this->_launchCommand('contactInfo', $params);
		}

		/**
		 * Edit contact details
		 *
		 * Example: 
		 *	```php
		 *	//we update a contact as a non-owner 
		 *	$res = null;
		 *	try {
		 *	 	$contact = array(
		 *	 		'firstName'=> 'donald',
		 *			'lastName' => 'trump',
		 *			'bodyForm' => 'IND',
		 *			'bodyName' => '',
		 *			'address1' => '1600 Pennsylvania Ave NW',
		 *			'address2' => '',
		 *			'zipCode'  => '20500',
		 *			'area'	   => 'DC',
		 *			'city'	   => 'Washington',
		 *			'country'  => 'US',
		 *			'phone'	   => '2024561111',
		 *			'fax'	   => '',
		 *			'email'    => 'donald.trump@gov.us',
		 *			'language' => 'EN',
		 *			'isOwner'  => 0
		 *		);
		 *		$res = $client->contactUpdate($idContact, $contact);   
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 * ```
		 *
		 * @param string $idContact the ID of the contact to be updated
		 * @param StructContact $contact the contact object containing the new values
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see contactUpdate API http://support.netim.com/en/wiki/ContactUpdate
		 */
		public function contactUpdate(string $idContact, array $datas): stdClass
		{
			$params[] = $idContact;
			$params[] = $datas;
			return $this->_launchCommand('contactUpdate', $params);
		}

		/**
		 * Edit contact details (for owner only) 
		 *
		 * Example
		 *	```php
		 *	//we update a owner contact
		 *	$res = null;
		 *	try
		 *	{
		 *			$contact = array(
		 *	 		'firstName'=> 'elon',
		 *			'lastName' => 'musk',
		 *			'bodyForm' => 'IND',
		 *			'bodyName' => '',
		 *			'address1' => '1 hollywood bvd',
		 *			'address2' => '',
		 *			'zipCode'  => '18022',
		 *			'area'	   => 'LA',
		 *			'city'	   => 'Los Angeles',
		 *			'country'  => 'US',
		 *			'phone'	   => '2024531111',
		 *			'fax'	   => '',
		 *			'email'    => 'elon.musk@tesla.com',
		 *			'language' => 'EN',
		 *			'isOwner'  => 1
		 *		);
		 *		$res = $client->contactOwnerUpdate($idContact, $contact); 
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $idContact the ID of the contact to be updated
		 * @param StructOwnerContact $contact the contact object containing the new values
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see contactOwnerUpdate API http://support.netim.com/en/wiki/ContactOwnerUpdate
		 * @see StructOwnerContact http://support.netim.com/en/wiki/StructOwnerContact
		 * 
		 */
		public function contactOwnerUpdate(string $idContact, array $datas): stdClass
		{
			$params[] = $idContact;
			$params[] = $datas;
			return $this->_launchCommand('contactOwnerUpdate', $params);
		}

		/**
		 * Deletes a contact object 
		 *
		 * Example1:
		 *	```php
		 *	$contactID = 'BJ007';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->contactDelete($contactID);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $idContact ID of the contact to be deleted
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see contactDelete API http://support.netim.com/en/wiki/ContactDelete
		 * @see StructOperationResponse API http://support.netim.com/en/wiki/StructOperationResponse
		 */
		public function contactDelete(string $idContact): stdClass
		{
			$params[] = $idContact;
			return $this->_launchCommand('contactDelete', $params);
		}

        # -------------------------------------------------
		# DOMAIN
		# -------------------------------------------------

		/**
		 * Checks if domain names are available for registration   
		 *
		 *  
		 * Example: Check one domain name
		 *	```php
		 *	$domain = "myDomain.com";
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainCheck($domain);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *	$domainCheckResponse = $res[0];
		 *	//continue processing
		 *	```
		 * @param string $domain Domain names to be checked 
		 * You can provide several domain names separated with semicolons. 
		 * Caution : 
		 *	- you can't mix different extensions during the same call 
		 *	- all the extensions don't accept a multiple checkDomain. See HasMultipleCheck in Category:Tld
		 *
		 * @throws NetimAPIException
		 *
		 * @return array An array of StructDomainCheckResponse
		 * 
		 * @see StructDomainCheckResponse http://support.netim.com/en/wiki/StructDomainCheckResponse
		 * @see DomainCheck API http://support.netim.com/en/wiki/DomainCheck
		 */
		public function domainCheck(string $domain):array
		{
			$params[] = $domain;
			return $this->_launchCommand('domainCheck', $params);
		}
        
        /**
		 * Requests a new domain registration 
		 *
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$idOwner = 'BJ008';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com'; 
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$duration = 1;
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainCreate($domain, $idOwner, $idAdmin, $idTech, $idBilling, $ns1, $ns2, $ns3, $ns4, $ns5, $duration);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $domain the name of the domain to create
		 * @param string $idOwner the id of the owner for the new domain
		 * @param string $idAdmin the id of the admin for the new domain
		 * @param string $idTech the id of the tech for the new domain
		 * @param string $idBilling the id of the billing for the new domain
		 *                          To get an ID, you can call contactCreate() with the appropriate information
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 * @param int $duration how long the domain will be created
		 * @param int $templateDNS OPTIONAL number of the template DNS created on netim.com/direct
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainCreate API http://support.netim.com/en/wiki/DomainCreate 
		 */
		public function domainCreate(string $domain, string $idOwner, string $idAdmin, string $idTech, string $idBilling, string $ns1, string $ns2, string $ns3, string $ns4, string $ns5, int $duration, int $templateDNS = null):stdClass
		{
			$params[] = strtolower($domain);

			$params[] = $idOwner;
			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;

			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;

			$params[] = $duration;

			if (!empty($templateDNS))
				$params[] = $templateDNS;

			return $this->_launchCommand('domainCreate', $params);
		}
        
        /**
		 * Returns all informations about a domain name 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainInfo($domain);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *
		 *	$domainInfo = $res;
		 *	//continue processing
		 *	```
		 * @param string $domain name of the domain
		 *
		 * @throws NetimAPIException
		 * 
		 * @return StructDomainInfo information about the domain
		 *
		 * @see domainInfo API http://support.netim.com/en/wiki/DomainInfo
		 */
		public function domainInfo(string $domain):stdClass
		{
			$params[] = $domain;
			return $this->_launchCommand('domainInfo', $params);
		}
        
        /**
		 * Requests a new domain registration during a launch phase
		 *
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$idOwner = 'BJ008';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com';
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$duration = 1;
		 *	$phase = 'GA';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainCreateLP($domain, $idOwner, $idAdmin, $idTech, $idBilling, $ns1, $ns2, $ns3, $ns4, $ns5, $duration, $phase);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $domain the name of the domain to create
		 * @param string $idOwner the id of the owner for the new domain
		 * @param string $idAdmin the id of the admin for the new domain
		 * @param string $idTech the id of the tech for the new domain
		 * @param string $idBilling the id of the billing for the new domain
		 *                          To get an ID, you can call contactCreate() with the appropriate information
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 * @param int $duration how long the domain will be created
		 * @param string $phase the id of the launch phase
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainCreateLP API http://support.netim.com/en/wiki/DomainCreateLP 
		 */
		public function domainCreateLP(string $domain, string $idOwner, string $idAdmin, string $idTech, string $idBilling, string $ns1, string $ns2, string $ns3, string $ns4, string $ns5, int $duration, string $phase):stdClass
		{
			$params[] = strtolower($domain);

			$params[] = $idOwner;
			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;

			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;

			$params[] = $duration;

			$params[] = $phase;

			return $this->_launchCommand('domainCreateLP', $params);
		}
        
        /**
		 * Deletes immediately a domain name 
		 * 
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainDelete($domain);
		 *		//equivalent to $res = $client->domainDelete($domain, 'NOW');
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $domain the name of the domain to delete
		 * @param string $typeDeletion OPTIONAL if the deletion is to be done now or not. Only supported value as of 2.0 is 'NOW'.
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainDelete API http://support.netim.com/en/wiki/DomainDelete
		 */
		public function domainDelete(string $domain, string $typeDelete = 'NOW'):stdClass
		{
			$params[] = $domain;
			$params[] = strtoupper($typeDelete);

			return $this->_launchCommand('domainDelete', $params);
		}
        
        /**
		 * Requests the transfer of a domain name to Netim 
		 *
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$authID = 'qlskjdlqkxlxkjlqksjdlkj';
		 *	$idOwner = 'BJ008';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com'; 
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainTransferIn($domain, $authID, $idOwner, $idAdmin, $idTech, $idBilling, $ns1, $ns2, $ns3, $ns4, $ns5);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of the domain to transfer
		 * @param string $authID authorisation code / EPP code (if applicable)
		 * @param string $idOwner a valid idOwner. Can also be #AUTO#
		 * @param string $idAdmin a valid idAdmin
		 * @param string $idTech a valid idTech
		 * @param string $idBilling a valid idBilling
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainTransferIn API http://support.netim.com/en/wiki/DomainTransferIn
		 */
		public function domainTransferIn(string $domain, string $authID, string $idOwner, string $idAdmin, string $idTech, string $idBilling, string $ns1, string $ns2, string $ns3, string $ns4, string $ns5):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $authID;

			$params[] = $idOwner;
			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;

			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;

			return $this->_launchCommand('domainTransferIn', $params);
		}
        
        /**
		 * Requests the transfer (with change of domain holder) of a domain name to Netim 
		 *
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$authID = 'qlskjdlqkxlxkjlqksjdlkj';
		 *	$idOwner = 'BJ008';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com'; 
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainTransferTrade($domain, $authID, $idOwner, $idAdmin, $idTech, $idBilling, $ns1, $ns2, $ns3, $ns4, $ns5);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain to transfer
		 * @param string $authID authorisation code / EPP code (if applicable)
		 * @param string $idOwner a valid idOwner.
		 * @param string $idAdmin a valid idAdmin
		 * @param string $idTech a valid idTech
		 * @param string $idBilling a valid idBilling
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainTransferTrade API http://support.netim.com/en/wiki/domainTransferTrade
		 */
		public function domainTransferTrade(string $domain, string $authID, string $idOwner, string $idAdmin, string $idTech, string $idBilling, string $ns1, string $ns2, string $ns3, string $ns4, string $ns5):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $authID;

			$params[] = $idOwner;
			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;

			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;

			return $this->_launchCommand('domainTransferTrade', $params);
		}
        
        /**
		 * Requests the internal transfer of a domain name from one Netim account to another. 
		 *
		 * Example:
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$authID = 'qlskjdlqkxlxkjlqksjdlkj';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com'; 
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainTransferTrade($domain, $authID, $idAdmin, $idTech, $idBilling, $ns1, $ns2, $ns3, $ns4, $ns5);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain to transfer
		 * @param string $authID authorisation code / EPP code (if applicable)
		 * @param string $idAdmin a valid idAdmin
		 * @param string $idTech a valid idTech
		 * @param string $idBilling a valid idBilling
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainInternalTransfer API http://support.netim.com/en/wiki/domainInternalTransfer
		 */
		public function domainInternalTransfer(string $domain, string $authID, string $idAdmin, string $idTech, string $idBilling, string $ns1, string $ns2, string $ns3, string $ns4, string $ns5):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $authID;

			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;

			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;

			return $this->_launchCommand('domainInternalTransfer', $params);
		}
        
        /**
		 * Renew a domain name for a new subscription period 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com'
		 *	$duration = 1;
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainCreate($domain, $duration)
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain the name of the domain to renew
		 * @param int $duration the duration of the renewal expressed in year. Must be at least 1 and less than the maximum amount
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainRenew API  http://support.netim.com/en/wiki/DomainRenew
		 */
		public function domainRenew(string $domain, int $duration):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $duration;

			return $this->_launchCommand('domainRenew', $params);
		}
        
        /**
		 * Restores a domain name in quarantine / redemption status
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainRestore($domain);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * @param string $domain name of the domain
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainRestore API http://support.netim.com/en/wiki/DomainRestore
		 */
		public function domainRestore(string $domain):stdClass
		{
			$params[] = strtolower($domain);
			return $this->_launchCommand('domainRestore', $params);
		}
        
        /**
		 * Updates the settings of a domain name
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$codePref = 'registrar_lock': //possible values are 'whois_privacy', 'registrar_lock', 'auto_renew', 'tag' or 'note'
		 *	$value = 1; // 1 or 0
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainSetPreference($domain, $codePref, $value);
		 *		//equivalent to $res = $client->domainSetRegistrarLock($domain,$value); each codePref has a corresponding helping function
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of the domain
		 * @param string $codePref setting to be modified. Accepted value are 'whois_privacy', 'registrar_lock', 'auto_renew', 'tag' or 'note'
		 * @param string $value new value for the settings. 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainSetPreference API http://support.netim.com/en/wiki/DomainSetPreference
		 * @see domainSetWhoisPrivacy, domainSetRegistrarLock, domainSetAutoRenew, domainSetTag, domainSetNote
		 */
		public function domainSetPreference(string $domain, string $codePref, string $value):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $codePref;
			$params[] = $value;
			return $this->_launchCommand('domainSetPreference', $params);
		}
        
        /**
		 * Requests the transfer of the ownership to another party
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$idOwner = 'BJ008';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainTransferOwner($domain, $idOwner);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of the domain
		 * @param string $idOwner id of the new owner
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainTransferOwner API http://support.netim.com/en/wiki/DomainTransferOwner
		 * @see function createContact
		 */
		public function domainTransferOwner(string $domain, string $idOwner):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $idOwner;
			return $this->_launchCommand('domainTransferOwner', $params);
		}
        
        /**
		 * Replaces the contacts of the domain (administrative, technical, billing) 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$idAdmin = 'BJ007';
		 *	$idTech = 'BJ007';
		 *	$idBilling = 'BJ007';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainChangeContact$domain, $idAdmin, $idTech, $idBilling);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain
		 * @param string $idAdmin id of the admin contact
		 * @param string $idTech id of the tech contact
		 * @param string $idBilling id of the billing contact
		 * 
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainChangeContact API http://support.netim.com/en/wiki/DomainChangeContact
		 * @see function createContact
		 */
		public function domainChangeContact(string $domain, string $idAdmin, string $idTech, string $idBilling):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $idAdmin;
			$params[] = $idTech;
			$params[] = $idBilling;
			return $this->_launchCommand('domainChangeContact', $params);
		}
        
		/**
		 * Replaces the DNS servers of the domain (redelegation) 
		 * 
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$ns1 = 'ns1.netim.com';
		 *	$ns2 = 'ns2.netim.com';
		 *	$ns3 = 'ns3.netim.com';
		 *	$ns4 = 'ns4.netim.com';
		 *	$ns5 = 'ns5.netim.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainChangeDNS($domain, $ns1, $ns2, $ns3, $ns4, $ns5);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain
		 * @param string $ns1 the name of the first dns
		 * @param string $ns2 the name of the second dns
		 * @param string $ns3 the name of the third dns
		 * @param string $ns4 the name of the fourth dns
		 * @param string $ns5 the name of the fifth dns
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainChangeDNS API http://support.netim.com/en/wiki/DomainChangeDNS
		 */
		public function domainChangeDNS(string $domain, string $ns1, string $ns2, string $ns3 = "", string $ns4 = "", string $ns5 = ""):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $ns1;
			$params[] = $ns2;
			$params[] = $ns3;
			$params[] = $ns4;
			$params[] = $ns5;
			return $this->_launchCommand('domainChangeDNS', $params);
		}
        
        /**
		 * Allows to sign a domain name with DNSSEC if it uses NETIM DNS servers 
		 * 
		 * @param string $domain name of the domain
		 * @param int $value New signature value 0 : unsign
		 * 										1 : sign 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainSetDNSsec API http://support.netim.com/en/wiki/DomainSetDNSsec
		 */
		public function domainSetDNSsec(string $domain, int $value):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $value;
			return $this->_launchCommand('domainSetDNSsec', $params);
		}
        
        /**
		 * Returns the authorization code to transfer the domain name to another registrar or to another client account 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainAuthID($domain, 0);
		 *		//$res = $client->domainAuthID($domain, 1); to send the authID in an email to the registrant of the domain
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of the domain to get the AuthID
		 * @param int $sendToRegistrant recipient of the AuthID. Possible value are 0 for the reseller and 1 for the registrant
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainAuthID API http://support.netim.com/en/wiki/DomainAuthID
		 */
		public function domainAuthID(string $domain, int $sendToRegistrant):stdClass
		{
			$params[] = $domain;
			$params[] = $sendToRegistrant;
			return $this->_launchCommand('domainAuthID', $params);
		}
        
        /**
		 * Release a domain name (managed by the reseller) to its registrant (who will become a direct customer at Netim) 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainRelease($domain);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain domain name to be released
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainRelease API http://support.netim.com/en/wiki/DomainRelease
		 */
		public function domainRelease(string $domain):stdClass
		{
			$params[] = strtolower($domain);
			return $this->_launchCommand('domainRelease', $params);
		}
        
		/**
		 * Adds a membership to the domain name 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com';
		 *	$token = 'qmksjdmqsjdmkl'; //replace with your token here
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainSetMembership($domain, $token);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of domain
		 * @param string $token membership number into the community
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainSetMembership API http://support.netim.com/en/wiki/DomainSetMembership
		 */
		public function domainSetMembership(string $domain, string $token):stdClass
		{
			$params[] = $domain;
			$params[] = $token;
			return $this->_launchCommand('domainSetMembership', $params);
		}
		
		/**
		 * Returns all available operations for a given TLD 
		 * 
		 * Example:
		 *	```php
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainTldInfo("COM"); //or 'com'
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *
		 *	$domainInfo = $res;
		 *	//continue processing
		 *	```
		 *	
		 * @param string $tld a valid tld without the dot before it
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructDomainTldInfo information about the tld
		 *
		 * @see domainTldInfo API http://support.netim.com/fr/wiki/DomainTldInfo
		 */
		public function domainTldInfo(string $tld):stdClass
		{
			$params[] = $tld;
			return $this->_launchCommand('domainTldInfo', $params);
		}
		
		/**
		 * Returns whois informations on given domain
		 * 
		 * Example:
		 *	```php
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainWhois("myDomain.com");
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something about the error
		 *	}
		 *
		 *	//continue processing
		 *	```
		 *	
		 * @param string $domain the domain's name
		 *
		 * @throws NetimAPIException
		 *
		 * @return string information about the domain
		 */
		public function domainWhois(string $domain):string
		{
			$params[] = strtolower($domain);
			return $this->_launchCommand('domainWhois', $params);
		}
		
		/**
		 * Allows to sign a domain name with DNSSEC if it doesn't use NETIM DNS servers 
		 * 
		 * @param string 	$domain name of the domain
		 * @param array		$DSRecords An object StructDSRecord
		 * @param int 		$flags
		 * @param int		$protocol
		 * @param int		$algo
		 * @param string	$pubKey
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 * 
		 * @see domainSetDNSSecExt API http://support.netim.com/en/wiki/DomainSetDNSSecExt
		 */
		public function domainSetDNSSecExt(string $domain, array $DSRecords, int $flags, int $protocol, int $algo, string $pubKey):stdClass
		{
			$params[] = $domain;
			$params[] = $DSRecords;
			$params[] = $flags;
			$params[] = $protocol;
			$params[] = $algo;
			$params[] = $pubKey;
			return $this->_launchCommand('domainSetDNSSecExt', $params);
		}

		/**
		 * Returns the list of all prices for each tld 
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructDomainPriceList[] 
		 * 
		 * @see domainPriceList API http://support.netim.com/en/wiki/DomainPriceList
		 */
		public function domainPriceList():array
		{
			return $this->_launchCommand('domainPriceList');
		}

		/**
		 * Allows to know a domain's price 
		 * 
		 * @param string $domain name of domain
		 * @param string $authID authorisation code (optional)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructQueryDomainPrice
		 * 
		 * @see queryDomainPrice API https://support.netim.com/en/wiki/QueryDomainPrice
		 * 
		 */
		public function queryDomainPrice(string $domain, string $authID = ""):stdClass
		{
			$params[] = $domain;
			if (!empty($authID)) $params[] = $authID;
			return $this->_launchCommand('queryDomainPrice', $params);
		}

		/**
		 * Allows to know if there is a claim on the domain name 
		 * 
		 * @param string $domain name of domain
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return int 0: no claim ; 1: at least one claim
		 * 
		 * @see queryDomainClaim API https://support.netim.com/en/wiki/QueryDomainClaim
		 * 
		 */
		public function queryDomainClaim(string $domain):int
		{
			$params[] = $domain;
			return $this->_launchCommand('queryDomainClaim', $params);
		}

		/**
		 * Returns all domains linked to the reseller account.
		 * 
		 * @param string $filter Domain name
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return array The filter applies onto the domain name
		 *
		 * @see queryDomainList API https://support.netim.com/en/wiki/QueryDomainList
		 *
		 */
		public function queryDomainList(string $filter = ""):array
		{
			$params[] = $filter;
			return $this->_launchCommand('queryDomainList', $params);
		}

		/**
		 * Resets all DNS settings from a template 
		 * 
		 * @param string 	$domain Domain name
		 * @param int 		$numTemplate Template number
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse
		 * 
		 * @see domainZoneInit API https://support.netim.com/en/wiki/DomainZoneInit
		 * 
		 * 
		 */
		public function domainZoneInit(string $domain, int $numTemplate):stdClass
		{
			$params[] = $domain;
			$params[] = $numTemplate;
			return $this->_launchCommand('domainZoneInit', $params);
		}

		/**
		 * Creates a DNS record into the domain zonefile
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com'
		 *	$subdomain = 'www';
		 *	$type = 'A';
		 *	$value = '192.168.0.1';
		 *	$options = array('service' => '', 'protocol' => '', 'ttl' => '3600', 'priority' => '', 'weight' => '', 'port' => '');
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainZoneCreate($domain, $subdomain, $type, $value, $options);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $domain name of the domain
		 * @param string $subdomain subdomain
		 * @param string $type type of DNS record. Accepted values are: 'A', 'AAAA', 'MX, 'CNAME', 'TXT', 'NS and 'SRV'
		 * @param string $value value of the new DNS record
		 * @param array $options StructOptionsZone : settings of the new DNS record 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainZoneCreate API http://support.netim.com/en/wiki/DomainZoneCreate
		 * @see StructOptionsZone http://support.netim.com/en/wiki/StructOptionsZone
		 */
		public function domainZoneCreate(string $domain, string $subdomain, string $type, string $value, array $options):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $subdomain;
			$params[] = $type;
			$params[] = $value;
			$params[] = $options;
			return $this->_launchCommand('domainZoneCreate', $params);
		}

		/**
		 * Deletes a DNS record into the domain's zonefile 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com'
		 *	$subdomain = 'www';
		 *	$type = 'A';
		 *	$value = '192.168.0.1';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainZoneDelete($domain, $subdomain, $type, $value);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain
		 * @param string $subdomain subdomain
		 * @param string $type type of DNS record. Accepted values are: 'A', 'AAAA', 'MX, 'CNAME', 'TXT', 'NS and 'SRV'
		 * @param string $value value of the new DNS record
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 * 
		 * @see domainZoneDelete API http://support.netim.com/en/wiki/DomainZoneDelete
		 */
		public function domainZoneDelete(string $domain, string $subdomain, string $type, string $value):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $subdomain;
			$params[] = $type;
			$params[] = $value;
			return $this->_launchCommand('domainZoneDelete', $params);
		}

		/**
		 * Resets the SOA record of a domain name 
		 *
		 * Example
		 *	```php
		 *	$domain = 'myDomain.com'
		 *	$ttl = 24;
		 *	$ttlUnit = 'H';
		 *	$refresh = 24;
		 *	$refreshUnit = 'H';
		 *	$retry = 24;
		 *	$retryUnit = 'H';
		 *	$expire = 24;
		 *	$expireUnit = 'H';
		 *	$minimum = 24;
		 *	$minimumUnit = 'H';
		 *	
		 *	try
		 *	{
		 *		$res = $client->domainZoneInitSoa($domain, $ttl, $ttlUnit, $refresh, $refreshUnit, $retry, $retryUnit, $expire, $expireUnit, $minimum, $minimumUnit);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 * 
		 * @param string $domain name of the domain
		 * @param int 	 $ttl time to live
		 * @param string $ttlUnit TTL unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $refresh Refresh delay
		 * @param string $refreshUnit Refresh unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $retry Retry delay
		 * @param string $retryUnit Retry unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $expire Expire delay
		 * @param string $expireUnit Expire unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $minimum Minimum delay
		 * @param string $minimumUnit Minimum unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 * 
		 * @see domainZoneInitSoa API http://support.netim.com/en/wiki/DomainZoneInitSoa
		 */
		public function domainZoneInitSoa(string $domain, int $ttl, string $ttlUnit, int $refresh, string $refreshUnit, int $retry, string $retryUnit, int $expire, string $expireUnit, int $minimum, string $minimumUnit):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $ttl;
			$params[] = $ttlUnit;
			$params[] = $refresh;
			$params[] = $refreshUnit;
			$params[] = $retry;
			$params[] = $retryUnit;
			$params[] = $expire;
			$params[] = $expireUnit;
			$params[] = $minimum;
			$params[] = $minimumUnit;

			return $this->_launchCommand('domainZoneInitSoa', $params);
		}

		/**
		 * Returns all DNS records of a domain name 
		 * 
		 * @param string $domain Domain name
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return array An array of StructQueryZoneList
		 *
		 * @see queryZoneList API https://support.netim.com/en/wiki/QueryZoneList
		 *
		 */
		public function queryZoneList(string $domain):array
		{
			$params[] = strtolower($domain);
			return $this->_launchCommand('queryZoneList', $params);
		}

		/**
		 * Creates an email address forwarded to recipients
		 *
		 * Example
		 *	```php
		 *	$mailBox = 'example@myDomain.com';
		 *	$recipients = 'address1@abc.com, address2@abc.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainMailFwdCreate($mailBox, $recipients);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $mailBox email adress (or * for a catch-all)
		 * @param string $recipients string list of email adresses (separated by commas)
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainMailFwdCreate API http://support.netim.com/en/wiki/DomainMailFwdCreate
		 */
		public function domainMailFwdCreate(string $mailBox, string $recipients):stdClass
		{
			$params[] = $mailBox;
			$params[] = $recipients;
			return $this->_launchCommand('domainMailFwdCreate', $params);
		}

		/**
		 * Deletes an email forward
		 *
		 * Example
		 *	```php
		 *	$mailBox = 'example@myDomain.com';
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainMailFwdDelete($mailBox);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $mailBox email adress 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainMailFwdDelete API http://support.netim.com/en/wiki/DomainMailFwdDelete
		 */
		public function domainMailFwdDelete(string $mailBox):stdClass
		{
			$params[] = strtolower($mailBox);
			return $this->_launchCommand('domainMailFwdDelete', $params);
		}

		/**
		 * Returns all email forwards for a domain name
		 * 
		 * @param string $domain Domain name
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return array An array of StructQueryMailFwdList
		 * 
		 * @see queryMailFwdList API https://support.netim.com/en/wiki/QueryMailFwdList
		 */
		public function queryMailFwdList(string $domain):array
		{
			$params[] = strtolower($domain);
			return $this->_launchCommand('queryMailFwdList', $params);
		}

		/**
		 * Creates a web forwarding 
		 *
		 * Example
		 *	```php
		 *	$fqdn = 'subdomain.myDomain.com';
		 *	$target = 'myDomain.com';
		 *	$type = 'DIRECT';
		 *	$options = $array('header'=>301, 'protocol'=>ftp, 'title'=>'', 'parking'=>'');
		 *	
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainWebFwdCreate($fqdn, $target, $type, $options);
		 *		//equivalent to $res = $client->domainWebFwdCreateTypeDirect($fqdn, $target, 301, 'ftp')
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $fqdn hostname (fully qualified domain name)
		 * @param string $target target of the web forwarding
		 * @param string $type type of the web forwarding. Accepted values are: "DIRECT", "IP", "MASKED" or "PARKING"
		 * @param array $options contains StructOptionsFwd : settings of the web forwarding. An array with keys: header, protocol, title and parking.
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainWebFwdCreate API http://support.netim.com/en/wiki/DomainWebFwdCreate
		 * @see StructOptionsFwd http://support.netim.com/en/wiki/StructOptionsFwd
		 */
		public function domainWebFwdCreate(string $fqdn, string $target, string $type, array $options):stdClass
		{
			$params[] = $fqdn;
			$params[] = $target;
			$params[] = strtoupper($type);
			$params[] = $options;
			return $this->_launchCommand('domainWebFwdCreate', $params);
		}

		/**
		 * Removes a web forwarding 
		 *
		 * Example
		 *	```php
		 *	$fqdn = 'subdomain.myDomain.com'
		 *	$res = null;
		 *	try
		 *	{
		 *		$res = $client->domainWebFwdDelete($fqdn);
		 *	}
		 *	catch (NetimAPIexception $exception)
		 *	{
		 *		//do something when operation had an error
		 *	}
		 *	//continue processing
		 *	```
		 *
		 * @param string $fqdn hostname, a fully qualified domain name
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainWebFwdDelete API http://support.netim.com/en/wiki/DomainWebFwdDelete
		 */
		public function domainWebFwdDelete(string $fqdn):stdClass
		{
			$params[] = $fqdn;
			return $this->_launchCommand('domainWebFwdDelete', $params);
		}

		/**
		 * Return all web forwarding of a domain name 
		 * 
		 * @param string $domain Domain name
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return array An array of StructQueryWebFwdList
		 *
		 * @see queryWebFwdList API https://support.netim.com/en/wiki/QueryWebFwdList
		 *
		 */
		public function queryWebFwdList(string $domain):array
		{
			$params[] = $domain;
			return $this->_launchCommand('queryWebFwdList', $params);
		}

		/**
		 * Creates a SSL redirection 
		 *		
		 * @param string $prod certificate type 
		 * @param string $duration period of validity (in years)
		 * @param StructCSR $CSRInfo object containing informations about the CSR 
		 * @param string $validation validation method of the CSR (either by email or file) : 	"file"
		 *																						"email:admin@yourdomain.com"
		 *																						"email:postmaster@yourdomain.com,webmaster@yourdomain.com" 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see domainWebFwdCreate API http://support.netim.com/en/wiki/DomainWebFwdCreate
		 * @see StructOptionsFwd http://support.netim.com/en/wiki/StructOptionsFwd
		 */
		public function sslCreate(string $prod, int $duration, array $CSRInfo, string $validation):stdClass
		{
			$params[] = $prod;
			$params[] = $duration;
			$params[] = $CSRInfo;
			$params[] = $validation;
			return $this->_launchCommand('sslCreate', $params);
		}

		/**
		 * Renew a SSL certificate for a new subscription period. 
		 *		
		 * @param string $IDSSL SSL certificate ID
		 * @param int $duration period of validity after the renewal (in years). Only the value 1 is valid
		 * 
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see sslRenew API http://support.netim.com/en/wiki/SslRenew
		 */
		public function sslRenew(string $IDSSL, int $duration): stdClass
		{
			$params[] = $IDSSL;
			$params[] = $duration;
			return $this->_launchCommand('sslRenew', $params);
		}

		/**
		 * Revokes a SSL Certificate. 
		 * 
		 * @param string $IDSSL SSL certificate ID
		 * 
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see sslRevoke API http://support.netim.com/en/wiki/SslRevoke
		 */
		public function sslRevoke(string $IDSSL):stdClass
		{
			$params[] = $IDSSL;
			return $this->_launchCommand('sslRevoke', $params);
		}

		/**
		 * Reissues a SSL Certificate. 
		 * 
		 * @param string $IDSSL SSL certificate ID
		 * @param StructCSR $CSRInfo Object containing informations about the CSR
		 * @param string $validation validation method of the CSR (either by email or file) : 	"file"
		 *																						"email:admin@yourdomain.com"
		 *																						"email:postmaster@yourdomain.com,webmaster@yourdomain.com"
		 * 
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see sslReIssue API http://support.netim.com/en/wiki/SslReIssue
		 * @see StructCSR http://support.netim.com/en/wiki/StructCSR
		 */
		public function sslReIssue(string $IDSSL, array $CSRInfo, string $validation): stdClass
		{
			$params[] = $IDSSL;
			$params[] = $CSRInfo;
			$params[] = $validation;
			return $this->_launchCommand('sslReIssue', $params);
		}

		/**
		 * Updates the settings of a SSL certificate. Currently, only the autorenew setting can be modified. 
		 * 
		 * @param string $IDSSL SSL certificate ID
		 * @param string $codePref Setting to be modified (auto_renew/to_be_renewed)
		 * @param string $value New value of the setting
		 * 
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see sslSetPreference API http://support.netim.com/en/wiki/SslSetPreference
		 */
		public function sslSetPreference(string $IDSSL, string $codePref, string $value): stdClass
		{
			$params[] = $IDSSL;
			$params[] = $codePref;
			$params[] = $value;

			return $this->_launchCommand('sslSetPreference', $params);
		}

		/**
		 * Returns all the informations about a SSL certificate
		 * 
		 * @param string $IDSSL SSL certificate ID
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructSSLInfo containing the SSL certificate informations 
		 * 
		 * @see sslInfo API http://support.netim.com/en/wiki/SslInfo
		 */
		public function sslInfo(string $IDSSL): stdClass
		{
			$params[] = $IDSSL;

			return $this->_launchCommand('sslInfo', $params);
		}

		/**
		 * Creates a web hosting
		 * 
		 * @param string $fqdn Fully qualified domain of the main vhost. Warning, the secondary vhosts will always be subdomains of this FQDN
		 * @param int $duration ID_TYPE_PROD of the hosting
		 * @param array $options 
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingCreate(string $fqdn, string $offer, int $duration, array $cms = array()): stdClass
		{
			$params[] = $fqdn;
			$params[] = $offer;
			$params[] = $duration;
			$params[] = $cms;

			return $this->_launchCommand('webHostingCreate', $params);
		}
		
		/**
		 * Get the unique ID of the hosting
		 * 
		 * @param string $fqdn Fully qualified domain of the main vhost.
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return string the unique ID of the hosting
		 */
		public function webHostingGetID(string $fqdn): string
		{
			$params[] = $fqdn;

			return $this->_launchCommand('webHostingGetID', $params);
		}
		
		/**
		 * Get informations about web hosting (generic infos, MUTU platform infos, ISPConfig ...)
		 * 
		 * @param string $id Hosting id
		 * @param array $additionalData determines which infos should be returned ("NONE", "ALL", "WEB", "VHOSTS", "SSL_CERTIFICATES",
		 * "PROTECTED_DIRECTORIES", "DATABASES", "DATABASE_USERS", "FTP_USERS", "CRON_TASKS", "MAIL", "DOMAIN_MAIL")
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructWebHostingInfo giving informations of the webhosting
		 */
		public function webHostingInfo(string $id, array $additionalData):array 
		{
			$params[] = $id;
			$params[] = $additionalData;

			return $this->_launchCommand('webHostingInfo', $params);
		}
		
		/**
		 * Renew a webhosting
		 * 
		 * @param string $id Hosting id
		 * @param int $duration Duration period (in months)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingRenew(string $id, int $duration):stdClass
		{
			$params[] = $id;
			$params[] = $duration;

			return $this->_launchCommand('webHostingRenew', $params);
		}

		/**
		 * Updates a webhosting
		 * 
		 * @param string $id Hosting id
		 * @param string $action Action name ("SetHold", "SetWebHold", "SetDBHold", "SetFTPHold", "SetMailHold", "SetPackage", "SetAutoRenew", "SetRenewReminder", "CalculateDiskUsage")
		 * @param array $params array("value"=>true/false) for all except SetPackage : array("offer"=>"SHWEB"/"SHLITE"/"SHMAIL"/"SHPREMIUM"/"SHSTART") and CalculateDiskUsage: array()
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingUpdate', $params);
		}

		/**
		 * Deletes a webhosting
		 * 
		 * @param $id Hosting id
		 * @param $typeDelete Only "NOW" is allowed
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDelete(string $id, string $typeDelete):stdClass
		{
			$params[] = $id;
			$params[] = $typeDelete;

			return $this->_launchCommand('webHostingDelete', $params);
		}

		/**
		 * Creates a vhost
		 * 
		 * @param $id Hosting id
		 * @param $fqdn Fqdn of the vhost
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingVhostCreate(string $id, string $fqdn):stdClass
		{
			$params[] = $id;
			$params[] = $fqdn;

			return $this->_launchCommand('webHostingVhostCreate', $params);
		}
		
		/**
		 * Change settings of a vhost
		 * 
		 * @param string $id Hosting id
		 * @param string $action Possible values :"SetStaticEngine", "SetPHPVersion",  "SetFQDN", "SetWebApplicationFirewall",
		 * "ResetContent", "FlushLogs", "AddAlias", "RemoveAlias", "LinkSSLCert", "UnlinkSSLCert", "EnableLetsEncrypt",
		 * "DisableLetsEncrypt", "SetRedirectHTTPS", "InstallWordpress", "InstallPrestashop", "SetHold"
		 * @param array $fparams Depends of the action
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingVhostUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingVhostUpdate', $params);
		}

		/**
		 * Deletes a vhost
		 * 
		 * @param string $id Hosting id
		 * @param string $fqdn of the vhost
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingVhostDelete(string $id, string $fqdn):stdClass
		{
			$params[] = $id;
			$params[] = $fqdn;

			return $this->_launchCommand('webHostingVhostDelete', $params);
		}

		/**
		 * Creates a mail domain
		 * 
		 * @param string $id Hosting id
		 * @param string $domain
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDomainMailCreate(string $id, string $domain):stdClass
		{
			$params[] = $id;
			$params[] = $domain;

			return $this->_launchCommand('webHostingDomainMailCreate', $params);
		}

		/**
		 * Change settings of mail domain based on the specified action
		 * 
		 * @param string $id Hosting id
		 * @param string $action Action name 
		 * @param array $fparams Parameters of the action
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDomainMailUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingDomainMailUpdate', $params);
		}

		/**
		 * Deletes a mail domain
		 * 
		 * @param string $id Hosting id
		 * @param string $domain
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDomainMailDelete(string $id, string $domain):stdClass
		{
			$params[] = $id;
			$params[] = $domain;

			return $this->_launchCommand('webHostingDomainMailDelete', $params);
		}

		/**
		 * Creates a SSL certificate
		 * 
		 * @param string $id Hosting id
		 * @param string $sslName Name of the certificate
		 * @param string $crt Content of the .crt file
		 * @param string $key Content of the .key file
		 * @param string $ca Content of the .ca file
		 * @param string $csr Content of the .csr file (optional)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingSSLCertCreate(string $id, string $sslName, string $crt, string $key, string $ca, string $csr=""):stdClass
		{
			$params[] = $id;
			$params[] = $sslName;
			$params[] = $crt;
			$params[] = $key;
			$params[] = $ca;
			$params[] = $csr;

			return $this->_launchCommand('webHostingSSLCertCreate', $params);
		}

		/**
		 * Delete a SSL certificate
		 * 
		 * @param string $id Hosting id
		 * @param string $sslName Name of the certificate
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingSSLCertDelete(string $id, string $sslName):stdClass
		{
			$params[] = $id;
			$params[] = $sslName;

			return $this->_launchCommand('webHostingSSLCertDelete', $params);
		}

		/**
		 * Creates a htpasswd protection on a directory
		 * 
		 * @param string $id Hosting id
		 * @param string $fqdn FQDN of the vhost which you want to protect
		 * @param string $pathSecured Path of the directory to protect starting from the selected vhost
		 * @param string $authname Text shown by browsers when accessing the directory
		 * @param string $username Login of the first user of the protected directory
		 * @param string $password Password of the first user of the protected directory
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingProtectedDirCreate(string $id, string $fqdn, string $pathSecured, string $authname, string $username, string $password):stdClass
		{
			$params[] = $id;
			$params[] = $fqdn;
			$params[] = $pathSecured;
			$params[] = $authname;
			$params[] = $username;
			$params[] = $password;

			return $this->_launchCommand('webHostingProtectedDirCreate', $params);
		}

		/**
		 * Change settings of a protected directory
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingProtectedDirUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingProtectedDirUpdate', $params);
		}

		/**
		 * Remove protection of a directory
		 * 
		 * @param string $id Hosting id
		 * @param string $fqdn Vhost's FQDN
		 * @param string $pathSecured Path of the protected directory starting from the selected vhost
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingProtectedDirDelete(string $id, string $fqdn, string $pathSecured):stdClass
		{
			$params[] = $id;
			$params[] = $fqdn;
			$params[] = $pathSecured;

			return $this->_launchCommand('webHostingProtectedDirDelete', $params);
		}

		/**
		 * Creates a cron task
		 * 
		 * @param string $id Hosting id
		 * @param string $fqdn Vhost's FDQN
		 * @param string $path Path to the script starting from the vhost's directory
		 * @param string $returnMethod "LOG", "MAIL" or "NONE"
		 * @param string $returnTarget 	When $returnMethod == "MAIL" : an email address
		 * 								When $returnMethod == "LOG" : a path to a log file starting from the vhost's directory
		 * @param string $mm
		 * @param string $hh
		 * @param string $jj
		 * @param string $mmm
		 * @param string $jjj
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingCronTaskCreate(string $id, string $fqdn, string $path, string $returnMethod, string $returnTarget, string $mm, string $hh, string $jj, string $mmm, string $jjj):stdClass
		{
			$params[] = $id;
			$params[] = $fqdn;
			$params[] = $path;
			$params[] = $returnMethod;
			$params[] = $returnTarget;
			$params[] = $mm;
			$params[] = $hh;
			$params[] = $jj;
			$params[] = $mmm;
			$params[] = $jjj;

			return $this->_launchCommand('webHostingCronTaskCreate', $params);
		}

		/**
		 * Change settings of a cron task
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 *
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingCronTaskUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingCronTaskUpdate', $params);
		}

		/**
		 * Delete a cron task
		 * 
		 * @param string $id Hosting id
		 * @param string $idCronTask 
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingCronTaskDelete(string $id, string $idCronTask):stdClass
		{
			$params[] = $id;
			$params[] = $idCronTask;

			return $this->_launchCommand('webHostingCronTaskDelete', $params);
		}

		/**
		 * Create a FTP user
		 * 
		 * @param string $id Hosting id
		 * @param string $username
		 * @param string $password
		 * @param string $rootDir User's root directory's path starting from the hosting root
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingFTPUserCreate(string $id, string $username, string $password, string $rootDir):stdClass
		{
			$params[] = $id;
			$params[] = $username;
			$params[] = $password;
			$params[] = $rootDir;

			return $this->_launchCommand('webHostingFTPUserCreate', $params);
		}

		/**
		 * Update a FTP user
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingFTPUserUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingFTPUserUpdate', $params);
		}

		/**
		 * Delete a FTP user
		 * 
		 * @param string $id Hosting id
		 * @param string $username
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingFTPUserDelete(string $id, string $username):stdClass
		{
			$params[] = $id;
			$params[] = $username;

			return $this->_launchCommand('webHostingFTPUserDelete', $params);
		}

		/**
		 * Create a database
		 * 
		 * @param string $id Hosting id
		 * @param string $dbName Name of the database (Must be preceded by the hosting id separated with a "_")
		 * @param string $version Wanted SQL version (Optional, the newest version will be chosen if left empty)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBCreate(string $id, string $dbName, string $version = ""):stdClass
		{
			$params[] = $id;
			$params[] = $dbName;
			$params[] = $version;

			return $this->_launchCommand('webHostingDBCreate', $params);
		}

		/**
		 * Update database settings
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingDBUpdate', $params);
		}

		/**
		 * Delete a database
		 * 
		 * @param string $id Hosting id
		 * @param string $dbName Name of the database
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBDelete(string $id, string $dbName):stdClass
		{
			$params[] = $id;
			$params[] = $dbName;

			return $this->_launchCommand('webHostingDBDelete', $params);
		}

		/**
		 * Create a database user
		 * 
		 * @param string $id Hosting id
		 * @param string $username
		 * @param string $password
		 * @param string $internalAccess "RW", "RO" or "NO"
		 * @param string $externalAccess "RW", "RO" or "NO"
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBUserCreate(string $id, string $username, string $password, string $internalAccess, string $externalAccess):stdClass
		{
			$params[] = $id;
			$params[] = $username;
			$params[] = $password;
			$params[] = $internalAccess;
			$params[] = $externalAccess;

			return $this->_launchCommand('webHostingDBUserCreate', $params);
		}

		/**
		 * Update database user's settings
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBUserUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingDBUserUpdate', $params);
		}

		/**
		 * Delete a database user
		 * 
		 * @param string $id Hosting id
		 * @param string $username
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingDBUserDelete(string $id, string $username):stdClass
		{
			$params[] = $id;
			$params[] = $username;

			return $this->_launchCommand('webHostingDBUserDelete', $params);
		}

		/**
		 * Create a mailbox
		 * 
		 * @param string $id Hosting id
		 * @param string $email
		 * @param string $password
		 * @param int $quota Disk space allocated to this box in MB
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingMailCreate(string $id, string $email, string $password, int $quota):stdClass
		{
			$params[] = $id;
			$params[] = $email;
			$params[] = $password;
			$params[] = $quota;

			return $this->_launchCommand('webHostingMailCreate', $params);
		}

		/**
		 * Update mailbox' settings
		 * 
		 * @param string $id Hosting id
		 * @param string $action Name of the action to perform
		 * @param array $fparams Parameters for the action (depends of the action)
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingMailUpdate(string $id, string $action, array $fparams):stdClass
		{
			$params[] = $id;
			$params[] = $action;
			$params[] = $fparams;

			return $this->_launchCommand('webHostingMailUpdate', $params);
		}

		/**
		 * Delete a mailbox
		 * 
		 * @param string $id Hosting id
		 * @param string $email
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingMailDelete(string $id, string $email):stdClass
		{
			$params[] = $id;
			$params[] = $email;

			return $this->_launchCommand('webHostingMailDelete', $params);
		}

		/**
		 * Create a mail redirection
		 * 
		 * @param string $id Hosting id
		 * @param string $source
		 * @param string[] $destination
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingMailFwdCreate(string $id, string $source, array $destination):stdClass
		{
			$params[] = $id;
			$params[] = $source;
			$params[] = $destination;

			return $this->_launchCommand('webHostingMailFwdCreate', $params);
		}

		/**
		 * Delete a mail redirection
		 * 
		 * @param string $id Hosting id
		 * @param string $source
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingMailFwdDelete(string $id, string $source):stdClass
		{
			$params[] = $id;
			$params[] = $source;

			return $this->_launchCommand('webHostingMailFwdDelete', $params);
		}

		/**
		 * Resets all DNS settings from a template 
		 * 
		 * @param string $domain
		 * @param int $profil
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingZoneInit(string $domain, int $profil):stdClass
		{
			$params[] = $domain;
			$params[] = $profil;

			return $this->_launchCommand('webHostingZoneInit', $params);
		}

		/**
		 * Resets the SOA record of a domain name for a webhosting
		 * 
		 * @param string $domain name of the domain
		 * @param int 	 $ttl time to live
		 * @param string $ttlUnit TTL unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $refresh Refresh delay
		 * @param string $refreshUnit Refresh unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $retry Retry delay
		 * @param string $retryUnit Retry unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $expire Expire delay
		 * @param string $expireUnit Expire unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 * @param int	 $minimum Minimum delay
		 * @param string $minimumUnit Minimum unit. Accepted values are: 'S', 'M', 'H', 'D', 'W'
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingZoneInitSoa(string $domain, int $ttl, string $ttlUnit, int $refresh, string $refreshUnit, int $retry, string $retryUnit, int $expire, string $expireUnit, int $minimum, string $minimumUnit):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = $ttl;
			$params[] = $ttlUnit;
			$params[] = $refresh;
			$params[] = $refreshUnit;
			$params[] = $retry;
			$params[] = $retryUnit;
			$params[] = $expire;
			$params[] = $expireUnit;
			$params[] = $minimum;
			$params[] = $minimumUnit;

			return $this->_launchCommand('webHostingZoneInitSoa', $params);
		}

		/**
		 * Returns all DNS records of a webhosting
		 * 
		 * @param string $domain Domain name
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructQueryZoneList[]
		 */
		public function webHostingZoneList(string $domain):array
		{
			$params[] = strtolower($domain);

			return $this->_launchCommand('webHostingZoneList', $params);
		}

		/**
		 * Creates a DNS record into the webhosting domain zonefile
		 *
		 * @param string $domain name of the domain
		 * @param string $subdomain subdomain
		 * @param string $type type of DNS record. Accepted values are: 'A', 'AAAA', 'MX, 'CNAME', 'TXT', 'NS and 'SRV'
		 * @param string $value value of the new DNS record
		 * @param array $options  StructOptionsZone : settings of the new DNS record 
		 *
		 * @throws NetimAPIException
		 *
		 * @return StructOperationResponse giving information on the status of the operation
		 *
		 * @see StructOptionsZone http://support.netim.com/en/wiki/StructOptionsZone
		 */
		public function webHostingZoneCreate(string $domain, string $subdomain, string $type, string $value, array $options):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = strtolower($subdomain);
			$params[] = $type;
			$params[] = $value;
			$params[] = $options;

			return $this->_launchCommand('webHostingZoneCreate', $params);
		}

		/**
		 * Deletes a DNS record into the webhosting domain zonefile
		 * 
		 * @param string $domain name of the domain
		 * @param string $subdomain subdomain
		 * @param string $type type of DNS record. Accepted values are: 'A', 'AAAA', 'MX, 'CNAME', 'TXT', 'NS and 'SRV'
		 * @param string $value value of the new DNS record
		 * 
		 * @throws NetimAPIException
		 * 
		 * @return StructOperationResponse giving information on the status of the operation
		 */
		public function webHostingZoneDelete(string $domain, string $subdomain, string $type, string $value):stdClass
		{
			$params[] = strtolower($domain);
			$params[] = strtolower($subdomain);
			$params[] = $type;
			$params[] = $value;

			return $this->_launchCommand('webHostingZoneDelete', $params);
		}

		//helpers for domainSetPreference
		public function domainSetRegistrarLock($domain, $value)
		{
			return $this->domainSetPreference($domain, 'registrar_lock', $value);
		}
		public function domainSetWhoisPrivacy($domain, $value)
		{
			return $this->domainSetPreference($domain, 'whois_privacy', $value);
		}
		public function domainSetAutoRenew($domain, $value)
		{
			return $this->domainSetPreference($domain, 'auto_renew', $value);
		}
		public function domainSetTag($domain, $value)
		{
			return $this->domainSetPreference($domain, 'tag', $value);
		}
		public function domainSetNote($domain, $value)
		{
			return $this->domainSetPreference($domain, 'note', $value);
		}

		public function domainWebFwdCreateTypeDirect($fqdn, $target, $header, $protocol)
		{
			$options['title'] = '';
			$options['header'] = $header;
			$options['protocol'] = $protocol;
			$options['parking'] = '';
			return $this->domainWebFwdCreate($fqdn, $target, 'DIRECT', $options);
		}

		public function domainWebFwdCreateTypeMasked($fqdn, $target, $protocol, $title)
		{
			$options['title'] = $title;
			$options['header'] = '';
			$options['protocol'] = $protocol;
			$options['parking'] = '';
			return $this->domainWebFwdCreate($fqdn, $target, 'MASKED', $options);
		}

		public function domainWebFwdCreateTypeIP($fqdn, $target)
		{
			$options['title'] = '';
			$options['header'] = '';
			$options['protocol'] = '';
			$options['parking'] = '';
			return $this->domainWebFwdCreate($fqdn, $target, 'IP', $options);
		}

		public function domainWebFwdCreateTypeParking($fqdn, $parking)
		{
			$options['title'] = '';
			$options['header'] = '';
			$options['protocol'] = '';
			$options['parking'] = $parking;
			return $this->domainWebFwdCreate($fqdn, '', 'PARKING', $options);
		}
	}
}
