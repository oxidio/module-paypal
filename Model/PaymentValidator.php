<?php
/**
 * This file is part of OXID eSales PayPal module.
 *
 * OXID eSales PayPal module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales PayPal module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales PayPal module.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2017
 */

namespace OxidEsales\PayPalModule\Model;

/**
 * This class is for checking validation of PayPal payment for user and basket amount
 */
class PaymentValidator
{

    /**
     * Basket price
     *
     * @var double $_dPrice
     */
    protected $_dPrice;

    /**
     * Config object
     *
     * @var \OxidEsales\Eshop\Core\Config $_oConfig
     */
    protected $_oConfig = null;

    /**
     * User object
     *
     * @var \OxidEsales\Eshop\Application\Model\User $_oUser
     */
    protected $_oUser = null;

    /**
     * Payment object
     *
     * @var \OxidEsales\Eshop\Application\Model\Payment $_oPayment
     */
    protected $_oPayment = null;

    /**
     * Check country in validator.
     *
     * @var bool
     */
    protected $_blCheckCountry = true;

    /**
     * Basket price setter
     *
     * @param double $dPrice
     */
    public function setPrice($dPrice)
    {
        $this->_dPrice = $dPrice;
    }

    /**
     * Basket price getter
     *
     * @return double
     */
    public function getPrice()
    {
        return $this->_dPrice;
    }

    /**
     * Config object setter
     *
     * @param \OxidEsales\Eshop\Core\Config $oConfig
     */
    public function setConfig($oConfig)
    {
        $this->_oConfig = $oConfig;
    }

    /**
     * Config object getter
     *
     * @return \OxidEsales\Eshop\Core\Config
     */
    public function getConfig()
    {
        return $this->_oConfig;
    }

    /**
     * User object setter
     *
     * @param \OxidEsales\Eshop\Application\Model\User $oUser
     */
    public function setUser($oUser)
    {
        $this->_oUser = $oUser;
    }

    /**
     * User object getter
     *
     * @return \OxidEsales\Eshop\Application\Model\User
     */
    public function getUser()
    {
        return $this->_oUser;
    }

    /**
     * Payment object setter
     *
     * @param \OxidEsales\Eshop\Application\Model\Payment $oPayment
     */
    public function setPayment($oPayment)
    {
        $this->_oPayment = $oPayment;
    }

    /**
     * Check country setter.
     *
     * @param boolean $blCheckCountry
     */
    public function setCheckCountry($blCheckCountry)
    {
        $this->_blCheckCountry = $blCheckCountry;
    }

    /**
     * Returns if country should be checked.
     *
     * @return boolean
     */
    public function getCheckCountry()
    {
        return $this->_blCheckCountry;
    }

    /**
     * Payment object getter
     *
     * @return \OxidEsales\Eshop\Application\Model\Payment
     */
    public function getPayment()
    {
        if (is_null($this->_oPayment)) {
            $oPayPalPayment = oxNew(\OxidEsales\Eshop\Application\Model\Payment::class);
            $oPayPalPayment->load('oxidpaypal');
            $this->setPayment($oPayPalPayment);
        }

        return $this->_oPayment;
    }

    /**
     * Checks if PayPal payment is active
     *
     * @return boolean
     */
    public function isPaymentActive()
    {
        $blResult = false;
        if ($oPayPalPayment = $this->getPayment()) {
            $blResult = $oPayPalPayment->oxpayments__oxactive->value ? true : false;
        }

        return $blResult;
    }


    /**
     * Checks if payment is valid according to config, user and basket amount.
     *
     * @return boolean
     */
    public function isPaymentValid()
    {
        $blIsValid = $this->isPaymentActive();

        if ($blIsValid && !is_null($this->getPrice())) {
            $blIsValid = $this->_checkPriceRange() && $this->_checkMinOrderPrice();
        }

        $oUser = $this->getUser();
        if ($blIsValid && $oUser && $oUser->hasAccount()) {
            $blIsValid = $this->_checkUserGroup();
        }

        if ($blIsValid && $oUser && $this->getCheckCountry()) {
            $blIsValid = $this->_checkUserCountry();
        }

        return $blIsValid;
    }

    /**
     * Checks if basket price is inside payment price range
     * If range is not set check returns true
     *
     * @return bool
     */
    protected function _checkPriceRange()
    {
        $blIsValid = true;

        $oPayPalPayment = $this->getPayment();

        if ($oPayPalPayment->oxpayments__oxfromamount->value != 0 ||
            $oPayPalPayment->oxpayments__oxtoamount->value != 0
        ) {

            $oCur = $this->getConfig()->getActShopCurrencyObject();
            $dPrice = $this->getPrice() / $oCur->rate;

            $blIsValid = (($dPrice >= $oPayPalPayment->oxpayments__oxfromamount->value) &&
                          ($dPrice <= $oPayPalPayment->oxpayments__oxtoamount->value));
        }

        return $blIsValid;
    }

    /**
     * Checks if basket price is higher than minimum order price
     * If min price is not set check returns true
     *
     * @return bool
     */
    protected function _checkMinOrderPrice()
    {
        $blIsValid = true;

        if ($iMinOrderPrice = $this->getConfig()->getConfigParam('iMinOrderPrice')) {
            $blIsValid = $this->getPrice() > $iMinOrderPrice;
        }

        return $blIsValid;
    }

    /**
     * Checks if user country is among payment countries
     * If payment countries are not set returns true
     *
     * @return bool
     */
    protected function _checkUserCountry()
    {
        $blIsValid = true;

        $oPayPalPayment = $this->getPayment();

        $aCountries = $oPayPalPayment->getCountries();
        if ($aCountries) {
            $blIsValid = false;
            foreach ($aCountries as $sCountryId) {
                if ($sCountryId === $this->_getShippingCountryId()) {
                    $blIsValid = true;
                    break;
                }
            }
        }

        return $blIsValid;
    }

    /**
     * Checks if user belongs group that is assigned to payment
     * If payment does not have any groups assigned returns true
     *
     * @return bool
     */
    protected function _checkUserGroup()
    {
        $blIsValid = true;

        $oPayPalPayment = $this->getPayment();
        $oGroups = $oPayPalPayment->getGroups();

        if ($oGroups && $oGroups->count() > 0) {
            $blIsValid = $this->_isUserAssignedToGroup($oGroups);
        }

        return $blIsValid;
    }

    /**
     * Checks whether user is assigned to given groups array.
     *
     * @param \OxidEsales\Eshop\Core\Model\ListModel $oGroups
     *
     * @return bool
     */
    protected function _isUserAssignedToGroup($oGroups)
    {
        $blIsValid = false;

        $oUser = $this->getUser();
        foreach ($oGroups as $oGroup) {
            if ($oUser->inGroup($oGroup->getId())) {
                $blIsValid = true;
                break;
            }
        }

        return $blIsValid;
    }

    /**
     * Returns shipping country ID.
     *
     * @return string
     */
    protected function _getShippingCountryId()
    {
        $oUser = $this->getUser();
        if ($oUser->getSelectedAddressId()) {
            $sCountryId = $oUser->getSelectedAddress()->oxaddress__oxcountryid->value;
        } else {
            $sCountryId = $oUser->oxuser__oxcountryid->value;
        }

        return $sCountryId;
    }
}