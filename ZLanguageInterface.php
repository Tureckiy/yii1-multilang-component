<?php

/**
 * Declare the interface 'IZLanguage'
 *
 ** @author Roman Sokolov

 * @package components
 */
interface ZLanguageInterface
{
    /**
     *
     * @param ZDbCommand $commandDb
     * @param type $queryParam array query params
     */
    public function makeQuery(ZDbCommand $commandDb, $queryParam = array());

    /**
     * check multilanguage on the system
     */
    public function isMultiLanguage();

    /**
     *
     */
    public function getUserStateLanguage();

    /**
     * get logo url (e.g. for layout)
     */
    public function getHomeUrl();

    /**
     *
     */
    public function checkLanguageCode($language);
}