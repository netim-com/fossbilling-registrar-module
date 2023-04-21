<?php

/** 
Normalized contact object derivated from the contact object
@created 2017-11-15
@lastUpdated 2023-04-20
@version 1.0.3
*/

namespace Netim
{
    use Netim\Contact;
    require_once dirname(__FILE__)."/../api/Contact.php";
    require_once "Normalization.php";

    /**
        * Handle all the normalization on fields needed for contact.
        * Will be usefull for contactCreate, contactUpdate, contactOwnerUpdate...
        * 
        *
        * @param $firstName string first name of the contact
        * @param $lastName string last name of the contact
        * @param $bodyName string the name of the organization
        * @param $address1 string first part of the address of the contact
        * @param $address2 string second part of the address of the contact
        * @param $zipCode string zipCode of the contact
        * @param $state string state of the contact
        * @param $countryCode string a valid country code defined by ISO 3166-1
        * @param $city string city of the contact
        * @param $phone string phone number of the contact
        * @param $email string email of the contact
        * @param $lang string language to display the message for the contact. Values are 'EN' or 'FR'
        * @param $isOwner int determines if the contact is an owner
        * @param $tmName string OPTIONAL trademark name
        * @param $tmNumber string OPTIONAL trademark number
        * @param $tmType string OPTIONAL trademark registry
        * @param $tmDate string OPTIONAL 
        * @param $companyNumber string OPTIONAL organization number
        * @param $vatNumber string OPTIONAL organisation VAT number
        * @param $birthDate string OPTIONAL date of birth
        * @param $birthZipCode string OPTIONAL postal code of birth
        * @param $birthCity string OPTIONAL city of birth
        * @param $birthCountry string OPTIONAL country of birth
        * @param $idNumber string OPTIONAL personal id number
        * @param $additional array OPTIONAL containing additional information that may be needed for some extensions but not all of them. See the extension documentation
        */
    class NormalizedContact extends Contact
    {
        private $norm;
        public function __construct(string $firstName, string $lastName, string $bodyName, string $address1, string $address2, string $zipCode,
        string $state, string $country, string $city, string $phone, string $email, string $lang, int $isOwner, ?string $tmName = "",
        ?string $tmNumber = "", ?string $tmType = "", ?string $tmDate = "", ?string $companyNumber = "", ?string $vatNumber = "", ?string $birthDate = "",
        ?string $birthZipCode = "", ?string $birthCity = "", ?string $birthCountry = "", ?string $idNumber = "", ?array $additional = array())
        {
            $this->norm = new Normalization();
            $lastName   = $this->norm->specialCharacter($lastName);
            $firstName  = $this->norm->specialCharacter($firstName);
            $bodyForm   = (empty($bodyName ?? "")) ? "IND" : "ORG";
            $bodyName   = $this->norm->specialCharacter($bodyName);
            $address1   = $this->norm->specialCharacter($address1);
            $address2   = $this->norm->specialCharacter($address2 ?? "");
            $country    = $this->norm->country($country);
            $area	    = $this->norm->state($state, $country);
            $city       = $this->norm->specialCharacter($city);
            $phone      = $this->norm->phoneNumber($phone, $country);
            $fax        = '';
            $language   = ($lang == 'FR') ? 'FR' : 'EN';
            parent::__construct($firstName, $lastName, $bodyForm, $bodyName ?? "", $address1, $address2 ?? "", $zipCode, $area, $city, $country, $phone, $fax, $email, $language, $isOwner,
            $tmName ?? "", $tmNumber ?? "", $tmType ?? "", $tmDate ?? "", $companyNumber ?? "", $vatNumber ?? "", $birthDate ?? "", $birthZipCode ?? "", $birthCity ?? "", $birthCountry ?? "", $idNumber ?? "", $additional ?? array());
        }


        /**
         * Set the value of firstName
         *
         * @return  self
         */ 
        public function setFirstName(string $firstName)
        {
            parent::setFirstName($this->norm->specialCharacter($firstName));

            return $this;
        }

        /**
         * Set the value of lastName
         *
         * @return  self
         */ 
        public function setLastName(string $lastName)
        {
            parent::setLastName($this->norm->specialCharacter($lastName));

            return $this;
        }

        /**
         * Set the value of bodyName
         *
         * @return  self
         */ 
        public function setBodyName(string $bodyName)
        {
            parent::setBodyForm((empty($bodyName)) ? "IND" : "ORG");
            parent::setBodyName($bodyName);

            return $this;
        }

        /**
         * Set the value of address1
         *
         * @return  self
         */ 
        public function setAddress1(string $address1)
        {
        parent::setAddress1($this->norm->specialCharacter($address1));

            return $this;
        }

        /**
         * Set the value of address2
         *
         * @return  self
         */ 
        public function setAddress2(string $address2)
        {
            parent::setAddress2($this->norm->specialCharacter($address2));

            return $this;
        }

        /**
         * Set the value of state
         *
         * @return  self
         */ 
        public function setState(string $state)
        {
            $this->setArea($this->norm->state($state, $this->getCountry()));

            return $this;
        }

        /**
         * Set the value of city
         *
         * @return  self
         */ 
        public function setCity(string $city)
        {
            parent::setCity($this->norm->specialCharacter($city));

            return $this;
        }

        /**
         * Set the value of phone
         * 
         * @return  self
         */
        public function setPhone(string $phone)
        {
            parent::setPhone($this->norm->phoneNumber($phone, $this->getCountry()));

            return $this;
        }
    }
}