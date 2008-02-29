<?php
/** 
*
* fulltext_sphinx [English]
*
* @package language
* @version $Id$
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* DO NOT CHANGE
*/
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'FULLTEXT_SPHINX_BIN_PATH'				=> 'Path to executables directory',
	'FULLTEXT_SPHINX_BIN_PATH_EXPLAIN'		=> 'If this path could not be determined automatically you have to enter the path to the directory in which the sphinx executables <samp>indexer</samp> and <samp>searchd</samp> reside.',
	'FULLTEXT_SPHINX_CONFIG_PATH'			=> 'Path to configuration directory',
	'FULLTEXT_SPHINX_CONFIG_PATH_EXPLAIN'	=> 'You should create this config directory outside the web accessable directories. It has to be writable by the user as which your webserver is running (often www-data or nobody).',
	'FULLTEXT_SPHINX_CONFIGURE_FIRST'		=> 'Before you create an index you have to enable and configure sphinx under GENERAL -> SERVER CONFIGURATION -> Search settings.',
	'FULLTEXT_SPHINX_DATA_PATH'				=> 'Path to data directory',
	'FULLTEXT_SPHINX_DATA_PATH_EXPLAIN'	=> 'You should create this directory outside the web accessable directories. It has to be writable by the user as which your webserver is running (often www-data or nobody). It will be used to store the indexes and log files.',
	'FULLTEXT_SPHINX_DELTA_POSTS'			=> 'Number of posts in frequently updated delta index',
	'FULLTEXT_SPHINX_DIRECTORY_NOT_FOUND'	=> 'The directory <strong>%s</strong> does not exist. Please correct your path settings.',
	'FULLTEXT_SPHINX_FILE_NOT_EXECUTABLE'	=> 'The file <strong>%s</strong> is not executable for the webserver.',
	'FULLTEXT_SPHINX_FILE_NOT_FOUND'		=> 'The file <strong>%s</strong> does not exist. Please correct your path settings.',
	'FULLTEXT_SPHINX_FILE_NOT_WRITABLE'	=> 'The file <strong>%s</strong> cannot be written by the webserver.',
	'FULLTEXT_SPHINX_LAST_SEARCHES'		=> 'Recent search queries',
	'FULLTEXT_SPHINX_MAIN_POSTS'			=> 'Number of posts in main index',
	'FULLTEXT_SPHINX_PORT'					=> 'Sphinx search deamon port',
	'FULLTEXT_SPHINX_PORT_EXPLAIN'			=> 'Port on which the sphinx search deamon on localhost listens. Leave empty to use the default 3312',
	'FULLTEXT_SPHINX_REQUIRES_EXEC'		=> 'The sphinx plugin for phpBB requires PHP’s <code>exec</code> function which is disabled on your system.',
	'FULLTEXT_SPHINX_WRONG_DATABASE'		=> 'The sphinx plugin for phpBB currently only supports MySQL',
	'FULLTEXT_SPHINX_STOPWORDS_FILE'		=> 'Stopwords activated',
	'FULLTEXT_SPHINX_STOPWORDS_FILE_EXPLAIN'	=> 'You can place a file called sphinx_stopwords.txt containing one word in each line in your config directory. If this file is present these words will be excluded from the indexing process.',
));

?>