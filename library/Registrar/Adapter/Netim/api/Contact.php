<?php

namespace Netim
{
    # -------------------------------------------------
    # CONTACT
    # -------------------------------------------------
    /**
     * This class is used to map a Contact between the API and the CMS
     * 
     * @see https://support.netim.com/en/wiki/StructContact
     */
    class Contact
    {
        private $firstName;
        private $lastName;
        private $bodyForm;
        private $bodyName;
        private $address1;
        private $address2;
        private $zipCode;
        private $area;
        private $city;
        private $country;
        private $phone;
        private $fax;
        private $email;
        private $language;
        private $isOwner;
        private $tmName;
        private $tmNumber;
        private $tmType;
        private $tmDate;
        private $companyNumber;
        private $vatNumber;
        private $birthDate;
        private $birthZipCode;
        private $birthCity;
        private $birthCountry;
        private $idNumber;
        private $additional;

        /**
        * Handle all the normalization on fields needed for contact.
        * Will be usefull for contactCreate, contactUpdate, contactOwnerUpdate...
        * 
        *
        *
        * @param $firstName string first name of the contact
        * @param $lastName string last name of the contact
        * @param $bodyForm string body form of the contact. Possible values are mostly IND and ORG (respectively for individual and organisation)
        * @param $bodyName string the name of the organization if $bodyForm == 'ORG'.
        * @param $address1 string first part of the address of the contact
        * @param $address2 string second part of the address of the contact
        * @param $zipCode string zipCode of the contact
        * @param $area string area code
        * @param $city string city of the contact
        * @param $country string a valid country code defined by ISO 3166-1
        * @param $phone string phone number of the contact
        * @param $fax string a fax number of the contact
        * @param $email string email of the contact
        * @param $language string language to display the message for the contact
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
        public function __construct(string $firstName, string $lastName, string $bodyForm, string $bodyName, string $address1, string $address2,
        string $zipCode, string $area, string $city, string $country, string $phone, string $fax, string $email, string $language, int $isOwner,
        string $tmName = "", string $tmNumber = "", string $tmType = "", string $tmDate = "", string $companyNumber = "", string $vatNumber = "",
        string $birthDate = "", string $birthZipCode = "", string $birthCity = "", string $birthCountry = "", string $idNumber = "", array $additional = array())
        {
            $this->firstName    = $firstName;
            $this->lastName     = $lastName;
            $this->bodyForm     = $bodyForm;
            $this->bodyName     = $bodyName;
            $this->address1     = $address1;
            $this->address2     = $address2;
            $this->zipCode      = $zipCode;
            $this->area         = $area;
            $this->city         = $city;
            $this->country      = $country;
            $this->phone        = $phone;
            $this->fax          = $fax;
            $this->email        = $email;
            $this->language     = $language;
            $this->isOwner      = $isOwner;
            $this->tmName       = $tmName;
            $this->tmNumber     = $tmNumber;
            $this->tmType       = $tmType;
            $this->tmDate       = $tmDate;
            $this->companyNumber= $companyNumber;
            $this->vatNumber    = $vatNumber;
            $this->birthDate    = $birthDate;
            $this->birthZipCode = $birthZipCode;
            $this->birthCity    = $birthCity;
            $this->birthCountry = $birthCountry;
            $this->idNumber     = $idNumber;
            $this->additional   =$additional;
        }
        
        /**
         * Used to communicate with the API
         * 
         * @return array Contact transformed into an array
         */
        public function to_array(): array
        {
            return array(
                'firstName'     => $this->firstName,
                'lastName'      => $this->lastName,
                'bodyForm'      => $this->bodyForm,
                'bodyName'      => $this->bodyName,
                'address1'      => $this->address1,
                'address2'      => $this->address2,
                'zipCode'       => $this->zipCode,
                'area'          => $this->area,
                'city'          => $this->city,
                'country'       => $this->country,
                'phone'         => $this->phone,
                'fax'           => $this->fax,
                'email'         => $this->email,
                'language'      => $this->language,
                'isOwner'       => $this->isOwner,
                'tmName'        => $this->tmName,
                'tmNumber'      => $this->tmNumber,
                'tmType'        => $this->tmType,
                'tmDate'        => $this->tmDate,
                'companyNumber' => $this->companyNumber,
                'vatNumber'     => $this->vatNumber,
                'birthDate'     => $this->birthDate,
                'birthZipCode'  => $this->birthZipCode,
                'birthCity'     => $this->birthCity,
                'birthCountry'  => $this->birthCountry,
                'idNumber'      => $this->idNumber,
                'additional'    => $this->additional
            );
        }

        /**
         * Used to communicate with the API
         * 
         * @return array Contact transformed into an array
         */
        public function denormalization(): Contact
        {
            require dirname(__FILE__)."/../lib/constNormalization.inc.php";
            if(isset($stateCodeToState[$this->country]) && isset($stateCodeToState[$this->country][$this->area]))
                $this->area = $stateCodeToState[$this->country][$this->area];
  
            return new Contact(
                $this->firstName,
                $this->lastName,
                $this->bodyForm,
                $this->bodyName,
                $this->address1,
                $this->address2,
                $this->zipCode,
                $this->area,
                $this->city,
                $this->country,
                $this->phone,
                $this->fax,
                $this->email,
                $this->language,
                $this->isOwner,
                $this->tmName,
                $this->tmNumber,
                $this->tmType,
                $this->tmDate,
                $this->companyNumber,
                $this->vatNumber,
                $this->birthDate,
                $this->birthZipCode,
                $this->birthCity,
                $this->birthCountry,
                $this->idNumber,
                $this->additional
            );
        }
        
        /**
         * Used to communicate with the API
         * 
         * @return Contact 
         */
        public static function object_to_contact(Object $arr): Contact
        {
            return new Contact(
                $arr->firstName,
                $arr->lastName,
                $arr->bodyForm,
                $arr->bodyName,
                $arr->address1,
                $arr->address2,
                $arr->zipCode,
                $arr->area,
                $arr->city,
                $arr->country,
                $arr->phone,
                $arr->fax,
                $arr->email,
                $arr->language,
                $arr->isOwner,
                $arr->tmName ?? "",
                $arr->tmNumber ?? "",
                $arr->tmType ?? "",
                $arr->tmDate ?? "",
                $arr->companyNumber ?? "",
                $arr->vatNumber ?? "",
                $arr->birthDate ?? "",
                $arr->birthZipCode ?? "",
                $arr->birthCity ?? "",
                $arr->birthCountry ?? "",
                $arr->idNumber ?? "",
                $arr->additional ?? array()
            );
        }


        /// Getters and setters

        /**
         * Get the value of firstName
         */ 
        public function getFirstName(): string
        {
            return $this->firstName;
        }

        /**
         * Set the value of firstName
         *
         * @return  self
         */ 
        public function setFirstName(string $firstName)
        {
            $this->firstName = $firstName;

            return $this;
        }


        /**
         * Get the value of lastName
         */ 
        public function getLastName(): string
        {
            return $this->lastName;
        }

        /**
         * Set the value of lastName
         *
         * @return  self
         */ 
        public function setLastName(string $lastName)
        {
            $this->lastName = $lastName;

            return $this;
        }

        /**
         * Get the value of bodyForm
         */ 
        public function getBodyForm(): string
        {
            return $this->bodyForm;
        }

        /**
         * Set the value of bodyForm
         *
         * @return  self
         */ 
        public function setBodyForm(string $bodyForm)
        {
            $this->bodyForm = $bodyForm;

            return $this;
        }

        /**
         * Get the value of bodyName
         */ 
        public function getBodyName(): string
        {
            return $this->bodyName;
        }

        /**
         * Set the value of bodyName
         *
         * @return  self
         */ 
        public function setBodyName(string $bodyName)
        {
            $this->bodyName = $bodyName;

            return $this;
        }

        /**
         * Get the value of address1
         */ 
        public function getAddress1(): string
        {
            return $this->address1;
        }

        /**
         * Set the value of address1
         *
         * @return  self
         */ 
        public function setAddress1(string $address1)
        {
            $this->address1 = $address1;

            return $this;
        }

        /**
         * Get the value of address2
         */ 
        public function getAddress2(): string
        {
            return $this->address2;
        }

        /**
         * Set the value of address2
         *
         * @return  self
         */ 
        public function setAddress2(string $address2)
        {
            $this->address2 = $address2;

            return $this;
        }

        /**
         * Get the value of zipCode
         */ 
        public function getZipCode(): string
        {
            return $this->zipCode;
        }

        /**
         * Set the value of zipCode
         *
         * @return  self
         */ 
        public function setZipCode(string $zipCode)
        {
            $this->zipCode = $zipCode;

            return $this;
        }

        /**
         * Get the value of area
         */ 
        public function getArea(): string
        {
            return $this->area;
        }

        /**
         * Set the value of area
         *
         * @return  self
         */ 
        public function setArea(string $area)
        {
            $this->area = $area;

            return $this;
        }

        /**
         * Get the value of city
         */ 
        public function getCity(): string
        {
            return $this->city;
        }

        /**
         * Set the value of city
         *
         * @return  self
         */ 
        public function setCity(string $city)
        {
            $this->city = $city;

            return $this;
        }

        /**
         * Get the value of country
         */ 
        public function getCountry(): string
        {
            return $this->country;
        }

        /**
         * Set the value of country
         *
         * @return  self
         */ 
        public function setCountry(string $country)
        {
            $this->country = $country;

            return $this;
        }

        /**
         * Get the value of phone
         */ 
        public function getPhone(): string
        {
            return $this->phone;
        }

        /**
         * Set the value of phone
         *
         * @return  self
         */ 
        public function setPhone(string $phone)
        {
            $this->phone = $phone;

            return $this;
        }

        /**
         * Get the value of fax
         */ 
        public function getFax(): string
        {
            return $this->fax;
        }

        /**
         * Set the value of fax
         *
         * @return  self
         */ 
        public function setFax(string $fax)
        {
            $this->fax = $fax;

            return $this;
        }

        /**
         * Get the value of email
         */ 
        public function getEmail(): string
        {
            return $this->email;
        }

        /**
         * Set the value of email
         *
         * @return  self
         */ 
        public function setEmail(string $email)
        {
            $this->email = $email;

            return $this;
        }

        /**
         * Get the value of language
         */ 
        public function getLanguage(): string
        {
            return $this->language;
        }

        /**
         * Set the value of language
         *
         * @return  self
         */ 
        public function setLanguage(string $language)
        {
            $this->language = $language;

            return $this;
        }

        /**
         * Get the value of isOwner
         */ 
        public function getIsOwner(): int
        {
            return $this->isOwner;
        }

        /**
         * Set the value of isOwner
         *
         * @return  self
         */ 
        public function setIsOwner(int $isOwner)
        {
            $this->isOwner = $isOwner;

            return $this;
        }

        /**
         * Get the value of tmName
         */ 
        public function getTmName(): string
        {
            return $this->tmName;
        }

        /**
         * Set the value of tmName
         *
         * @return  self
         */ 
        public function setTmName(string $tmName)
        {
            $this->tmName = $tmName;

            return $this;
        }

        /**
         * Get the value of tmNumber
         */ 
        public function getTmNumber(): string
        {
            return $this->tmNumber;
        }

        /**
         * Set the value of tmNumber
         *
         * @return  self
         */ 
        public function setTmNumber(string $tmNumber)
        {
            $this->tmNumber = $tmNumber;

            return $this;
        }

        /**
         * Get the value of tmType
         */ 
        public function getTmType(): string
        {
            return $this->tmType;
        }

        /**
         * Set the value of tmType
         *
         * @return  self
         */ 
        public function setTmType(string $tmType)
        {
            $this->tmType = $tmType;

            return $this;
        }

        /**
         * Get the value of companyNumber
         */ 
        public function getCompanyNumber(): string
        {
            return $this->companyNumber;
        }

        /**
         * Set the value of companyNumber
         *
         * @return  self
         */ 
        public function setCompanyNumber(string $companyNumber)
        {
            $this->companyNumber = $companyNumber;

            return $this;
        }

        /**
         * Get the value of vatNumber
         */ 
        public function getVatNumber(): string
        {
            return $this->vatNumber;
        }

        /**
         * Set the value of vatNumber
         *
         * @return  self
         */ 
        public function setVatNumber(string $vatNumber)
        {
            $this->vatNumber = $vatNumber;

            return $this;
        }

        /**
         * Get the value of birthDate
         */ 
        public function getBirthDate(): string
        {
            return $this->birthDate;
        }

        /**
         * Set the value of birthDate
         *
         * @return  self
         */ 
        public function setBirthDate(string $birthDate)
        {
            $this->birthDate = $birthDate;

            return $this;
        }

        /**
         * Get the value of birthZipCode
         */ 
        public function getBirthZipCode(): string
        {
            return $this->birthZipCode;
        }

        /**
         * Set the value of birthZipCode
         *
         * @return  self
         */ 
        public function setBirthZipCode(string $birthZipCode)
        {
            $this->birthZipCode = $birthZipCode;

            return $this;
        }

        /**
         * Get the value of birthCity
         */ 
        public function getBirthCity(): string
        {
            return $this->birthCity;
        }

        /**
         * Set the value of birthCity
         *
         * @return  self
         */ 
        public function setBirthCity(string $birthCity)
        {
            $this->birthCity = $birthCity;

            return $this;
        }

        /**
         * Get the value of birthCountry
         */ 
        public function getBirthCountry(): string
        {
            return $this->birthCountry;
        }

        /**
         * Set the value of birthCountry
         *
         * @return  self
         */ 
        public function setBirthCountry(string $birthCountry)
        {
            $this->birthCountry = $birthCountry;

            return $this;
        }

        /**
         * Get the value of idNumber
         */ 
        public function getIdNumber(): string
        {
            return $this->idNumber;
        }

        /**
         * Set the value of idNumber
         *
         * @return  self
         */ 
        public function setIdNumber(string $idNumber)
        {
            $this->idNumber = $idNumber;

            return $this;
        }

        /**
         * Get the value of additional
         */ 
        public function getAdditional(): array
        {
            return $this->additional;
        }

        /**
         * Set the value of additional
         *
         * @return  self
         */ 
        public function setAdditional(array $additional)
        {
            $this->additional = $additional;

            return $this;
        }

        /**
         * Get the value of tmDate
         */ 
        public function getTmDate()
        {
            return $this->tmDate;
        }

        /**
         * Set the value of tmDate
         *
         * @return  self
         */ 
        public function setTmDate($tmDate)
        {
            $this->tmDate = $tmDate;

            return $this;
        }

        public function equals(Contact $c): bool
        {
            return  $this->firstName     == $c->getFirstName()     &&
                    $this->lastName      == $c->getLastName()      &&
                    $this->bodyForm      == $c->getBodyForm()      &&
                    $this->bodyName      == $c->getBodyName()      &&
                    $this->address1      == $c->getAddress1()      &&
                    $this->address2      == $c->getAddress2()      &&
                    $this->zipCode       == $c->getZipCode()       &&
                    $this->area          == $c->getArea()          &&
                    $this->city          == $c->getCity()          &&
                    $this->country       == $c->getCountry()       &&
                    $this->phone         == $c->getPhone()         &&
                    $this->fax           == $c->getFax()           &&
                    $this->email         == $c->getEmail()         &&
                    $this->language      == $c->getLanguage()      &&
                    $this->isOwner       == $c->getIsOwner()       &&
                    $this->tmName        == $c->getTmName()        &&
                    $this->tmNumber      == $c->getTmNumber()      &&
                    $this->tmType        == $c->getTmType()        &&
                    $this->tmDate        == $c->getTmDate()        &&
                    $this->companyNumber == $c->getCompanyNumber() &&
                    $this->vatNumber     == $c->getVatNumber()     &&
                    $this->birthDate     == $c->getBirthDate()     &&
                    $this->birthZipCode  == $c->getBirthZipCode()  &&
                    $this->birthCity     == $c->getBirthCity()     &&
                    $this->birthCountry  == $c->getBirthCountry()  &&
                    $this->idNumber      == $c->getIdNumber()      &&
                    $this->additional    == $c->getAdditional();
        }
    }
}