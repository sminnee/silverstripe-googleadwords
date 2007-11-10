<?php
/**
 * This Allows a selection of how the adwords module should communicate with google.
 * The current communication is done by scraping the main adwords interface.
 *   Pros:
 *      Free
 *      Works for all adwords accounts
 *      Simple configuration
 *   Cons:
 *      Uncondoned by Google.
 *      Liable to stop working, or work eratically if the site changes it's design
 *      Fragile Construction.
 *
 *  If google releases its api for adwords in a more useful form, a driver can be 
 *  written using the api without affecting the interface, and plugged in here.
 */
GoogleAdwords::set_driver("Scraping");

DataObject::add_extension('SiteTree', 'GoogleAdwords');

/**
 * Register the Adwords URL space.
 */
Director::addRules(105, array(
	'admin/adwords/$Page/$Action' => 'Adwords_Controller'));
?>
