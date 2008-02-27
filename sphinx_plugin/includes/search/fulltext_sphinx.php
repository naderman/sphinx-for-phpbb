<?php
/** 
*
* @package search
* @version $Id: fulltext_sphinx.php,v 1.1.1.1 2007/03/18 21:07:10 naderman Exp $
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @ignore
*/
include_once($phpbb_root_path . 'includes/search/search.' . $phpEx);
require($phpbb_root_path . "includes/sphinxapi.php");
define('INDEXER_NAME', 'indexer');
define('SEARCHD_NAME', 'searchd');
define('SPHINX_TABLE', table_prefix() . 'sphinx');

/**
* Returns the global table prefix
* This function is necessary as this file is sometimes included in a function
* and table_prefix is in global space.
*/
function table_prefix() {
	global $table_prefix;
	return $table_prefix;
}

/**
* fulltext_sphinx
* Fulltext search based on the sphinx search deamon
* @package search
*/
class fulltext_sphinx extends search_backend
{
	var $stats = array();
	var $word_length = array();
	var $split_words = array();
	var $search_query;
	var $common_words = array();
	var $id;

	function fulltext_sphinx(&$error)
	{
		global $config;

		$this->id = $config['avatar_salt'];

		if ($config['fulltext_sphinx_bin_path'])
		{
			if (!file_exists($config['fulltext_sphinx_data_path'] . 'searchd.pid'))
			{
				// todo: unlink all data/*.spl files
				$cwd = getcwd();
				chdir($config['fulltext_sphinx_bin_path']);
				exec('./' . SEARCHD_NAME . ' --config ' . $config['fulltext_sphinx_config_path'] . 'sphinx.conf > /dev/null 2>&1 &');
				chdir($cwd);
			}
			$this->sphinx = new SphinxClient ();
			$this->sphinx->SetServer("localhost", (isset($config['fulltext_sphinx_port']) && $config['fulltext_sphinx_port']) ? (int) $config['fulltext_sphinx_port'] : 3312); // we only support localhost for now
		}

		$config['fulltext_sphinx_min_word_len'] = 2;
		$config['fulltext_sphinx_max_word_len'] = 400;

		$error = false;
	}

	/**
	* Checks permissions and paths, if everything is correct it generates the config file
	*/
	function init()
	{
		global $db, $user, $config;

		if ($db->sql_layer != 'mysql' && $db->sql_layer != 'mysql4' && $db->sql_layer != 'mysqli')
		{
			return $user->lang['FULLTEXT_SPHINX_WRONG_DATABASE'];
		}

		if ($error = $this->config_updated())
		{
			return $error;
		}

		// move delta to main index each hour
		set_config('search_gc', 3600);

		return false;
	}

	function config_updated()
	{
		global $db, $user, $config, $phpbb_root_path, $phpEx;

		$paths = array('fulltext_sphinx_bin_path', 'fulltext_sphinx_config_path', 'fulltext_sphinx_data_path');

		// add trailing slash if it's not present
		foreach ($paths as $path)
		{
			if ($config[$path] && substr($config[$path], -1) != '/')
			{
				set_config($path, $config[$path] . '/');
			}
		}

		$executables = array(
			$config['fulltext_sphinx_bin_path'] . INDEXER_NAME,
			$config['fulltext_sphinx_bin_path'] . SEARCHD_NAME,
		);

		foreach ($executables as $executable)
		{
			if (!file_exists($executable))
			{
				return sprintf($user->lang['FULLTEXT_SPHINX_FILE_NOT_FOUND'], $executable);
			}

			if (!function_exists('exec')) {
				return $user->lang['FULLTEXT_SPHINX_REQUIRES_EXEC'];
			}

			$output = array();
			@exec($executable, $output);

			$output = implode("\n", $output);
			if (strpos($output, 'Sphinx ') === false)
			{
				return sprintf($user->lang['FULLTEXT_SPHINX_FILE_NOT_EXECUTABLE'], $executable);
			}
		}

		$writable_paths = array($config['fulltext_sphinx_config_path'], $config['fulltext_sphinx_data_path'], $config['fulltext_sphinx_data_path'] . 'log');

		foreach ($writable_paths as $i => $path)
		{
			// make sure directory exists
			if (!file_exists($path))
			{
				return sprintf($user->lang['FULLTEXT_SPHINX_DIRECTORY_NOT_FOUND'], $path);
			}
	
			// now check if it is writable by storing a simple file
			$filename = $path . 'write_test';
			$fp = @fopen($filename, 'wb');
			if ($fp === false)
			{
				return sprintf($user->lang['FULLTEXT_SPHINX_FILE_NOT_WRITABLE'], $filename);
			}
			@fclose($fp);
	
			@unlink($filename);
			
			if ($i == 1)
			{
				if (!is_dir($path . 'log'))
				{
					mkdir($path . 'log');
				}
			}
		}

		include ($phpbb_root_path . 'config.' . $phpEx);

		// now that we're sure everything was entered correctly, generate a config for the index
		// we misuse the avatar_salt for this, as it should be unique ;-)

		if (!class_exists('sphinx_config'))
		{
			include($phpbb_root_path . 'includes/functions_sphinx.php');
		}

		if (!file_exists($config['fulltext_sphinx_config_path'] . 'sphinx.conf'))
		{
			$filename = $config['fulltext_sphinx_config_path'] . 'sphinx.conf';
			$fp = @fopen($filename, 'wb');
			if ($fp === false)
			{
				return sprintf($user->lang['FULLTEXT_SPHINX_FILE_NOT_WRITABLE'], $filename);
			}
			@fclose($fp);
		}

		$config_object = new sphinx_config($config['fulltext_sphinx_config_path'] . 'sphinx.conf');

		$config_data = array(
			"source source_phpbb_{$this->id}_main" => array(
				array('type',						'mysql'),
				array('strip_html',					'0'),
				array('index_html_attrs',			''),
				array('sql_host',					$dbhost),
				array('sql_user',					$dbuser),
				array('sql_pass',					$dbpasswd),
				array('sql_db',						$dbname),
				array('sql_port',					$dbport),
				array('sql_query_pre',				'REPLACE INTO ' . SPHINX_TABLE . ' SELECT 1, MAX(post_id) FROM ' . POSTS_TABLE . ''),
				array('sql_query_range',			'SELECT MIN(post_id), MAX(post_id) FROM ' . POSTS_TABLE . ''),
				array('sql_range_step',				'5000'),
				array('sql_query',					'SELECT
						p.post_id AS id,
						p.forum_id,
						p.topic_id,
						p.poster_id,
						IF (p.post_id = t.topic_first_post_id, 1, 2) as topic_first_post,
						p.post_time,
						p.post_subject,
						p.post_subject as title,
						p.post_text as data
					FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t
					WHERE
						p.topic_id = t.topic_id
						AND p.post_id >= $start AND p.post_id <= $end'),
				array('sql_query_post',				''),
				array('sql_query_post_index',		'REPLACE INTO ' . SPHINX_TABLE . ' ( counter_id, max_doc_id ) VALUES ( 1, $maxid )'),
				array('sql_query_info',				'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = $id'),
				array('sql_group_column',			'forum_id'),
				array('sql_group_column',			'topic_id'),
				array('sql_group_column',			'poster_id'),
				array('sql_group_column',			'topic_first_post'),
				array('sql_date_column'	,			'post_time'),
				array('sql_str2ordinal_column',	'post_subject'),
			),
			"source source_phpbb_{$this->id}_delta : source_phpbb_{$this->id}_main" => array(
				array('sql_query_pre',				''),
				array('sql_query_range',			''),
				array('sql_range_step',				''),
				array('sql_query',					'SELECT
						p.post_id AS id,
						p.forum_id,
						p.topic_id,
						p.poster_id,
						IF (p.post_id = t.topic_first_post_id, 1, 2) as topic_first_post,
						p.post_time,
						p.post_subject,
						p.post_subject as title,
						p.post_text as data
					FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t
					WHERE
						p.topic_id = t.topic_id
						AND p.post_id >=  ( SELECT max_doc_id FROM ' . SPHINX_TABLE . ' WHERE counter_id=1 )'),
			),
			"index index_phpbb_{$this->id}_main" => array(
				array('path',						$config['fulltext_sphinx_data_path'] . "index_phpbb_{$this->id}_main"),
				array('source',						"source_phpbb_{$this->id}_main"),
				array('docinfo',					'extern'),
				array('morphology',					'none'),
				array('stopwords',					(isset($config['fulltext_sphinx_stop_words_file'])) ? $config['fulltext_sphinx_stop_words_file'] : ''),
				array('min_word_len',				'2'),
				array('charset_type',				'utf-8'),
				array('charset_table',				'U+FF10..U+FF19->0..9, 0..9, U+FF41..U+FF5A->a..z, U+FF21..U+FF3A->a..z, A..Z->a..z, a..z, U+0149, U+017F, U+0138, U+00DF, U+00FF, U+00C0..U+00D6->U+00E0..U+00F6, U+00E0..U+00F6, U+00D8..U+00DE->U+00F8..U+00FE, U+00F8..U+00FE, U+0100->U+0101, U+0101, U+0102->U+0103, U+0103, U+0104->U+0105, U+0105, U+0106->U+0107, U+0107, U+0108->U+0109, U+0109, U+010A->U+010B, U+010B, U+010C->U+010D, U+010D, U+010E->U+010F, U+010F, U+0110->U+0111, U+0111, U+0112->U+0113, U+0113, U+0114->U+0115, U+0115, U+0116->U+0117, U+0117, U+0118->U+0119, U+0119, U+011A->U+011B, U+011B, U+011C->U+011D, U+011D, U+011E->U+011F, U+011F, U+0130->U+0131, U+0131, U+0132->U+0133, U+0133, U+0134->U+0135, U+0135, U+0136->U+0137, U+0137, U+0139->U+013A, U+013A, U+013B->U+013C, U+013C, U+013D->U+013E, U+013E, U+013F->U+0140, U+0140, U+0141->U+0142, U+0142, U+0143->U+0144, U+0144, U+0145->U+0146, U+0146, U+0147->U+0148, U+0148, U+014A->U+014B, U+014B, U+014C->U+014D, U+014D, U+014E->U+014F, U+014F, U+0150->U+0151, U+0151, U+0152->U+0153, U+0153, U+0154->U+0155, U+0155, U+0156->U+0157, U+0157, U+0158->U+0159, U+0159, U+015A->U+015B, U+015B, U+015C->U+015D, U+015D, U+015E->U+015F, U+015F, U+0160->U+0161, U+0161, U+0162->U+0163, U+0163, U+0164->U+0165, U+0165, U+0166->U+0167, U+0167, U+0168->U+0169, U+0169, U+016A->U+016B, U+016B, U+016C->U+016D, U+016D, U+016E->U+016F, U+016F, U+0170->U+0171, U+0171, U+0172->U+0173, U+0173, U+0174->U+0175, U+0175, U+0176->U+0177, U+0177, U+0178->U+00FF, U+00FF, U+0179->U+017A, U+017A, U+017B->U+017C, U+017C, U+017D->U+017E, U+017E, U+4E00..U+9FFF'),
				array('min_prefix_len',				'0'),
				array('min_infix_len',				'0'),
			),
			"index index_phpbb_{$this->id}_delta : index_phpbb_{$this->id}_main" => array(
				array('path',						$config['fulltext_sphinx_data_path'] . "index_phpbb_{$this->id}_delta"),
				array('source',						"source_phpbb_{$this->id}_delta"),
			),
			'indexer' => array(
				array('mem_limit',					'512M'),
			),
			'searchd' => array(
				array('address'	,					'127.0.0.1'),
				array('port',						($config['fulltext_sphinx_port']) ? $config['fulltext_sphinx_port'] : '3312'),
				array('log',						$config['fulltext_sphinx_data_path'] . "log/searchd.log"),
				array('query_log',					$config['fulltext_sphinx_data_path'] . "log/sphinx-query.log"),
				array('read_timeout',				'5'),
				array('max_children',				'30'),
				array('pid_file',					$config['fulltext_sphinx_data_path'] . "searchd.pid"),
				array('max_matches',				'1000'),
			),
		);

		$non_unique = array('sql_group_column' => true, 'sql_date_column' => true, 'sql_str2ordinal_column' => true);

		foreach ($config_data as $section_name => $section_data)
		{
			$section = &$config_object->get_section_by_name($section_name);
			if (!$section)
			{
				$section = &$config_object->add_section($section_name);
			}

			foreach ($non_unique as $key => $void)
			{
				$section->delete_variables_by_name($key);
			}

			foreach ($section_data as $entry)
			{
				$key = $entry[0];
				$value = $entry[1];

				if (!isset($non_unique[$key]))
				{
					$variable = &$section->get_variable_by_name($key);
					if (!$variable)
					{
						$variable = &$section->create_variable($key, $value);
					}
					else
					{
						$variable->set_value($value);
					}
				}
				else
				{
					$variable = &$section->create_variable($key, $value);
				}
			}
		}

		$config_object->write($config['fulltext_sphinx_config_path'] . 'sphinx.conf');

		return false;
	}

	/**
	* Splits keywords entered by a user into an array of words stored in $this->split_words
	* Stores the tidied search query in $this->search_query
	*
	* @param string $keywords Contains the keyword as entered by the user
	* @param string $terms is either 'all' or 'any'
	* @return false if no valid keywords were found and otherwise true
	*/
	function split_keywords(&$keywords, $terms)
	{
		global $config;

		if ($terms == 'all')
		{
			$match		= array('#\sand\s#i', '#\sor\s#i', '#\snot\s#i', '#\+#', '#-#', '#\|#');
			$replace	= array(' & ', ' | ', '  - ', ' +', ' -', ' |');

			$replacements = 0;
			$keywords = preg_replace($match, $replace, $keywords);
			if (strpos($keywords, '&') !== false || strpos($keywords, '+') !== false || strpos($keywords, '-') !== false || strpos($keywords, '|') !== false)
			{
				$this->sphinx->SetMatchMode(SPH_MATCH_BOOLEAN);
			}
			else
			{
				$this->sphinx->SetMatchMode(SPH_MATCH_ALL);
			}
		}
		else
		{
			$this->sphinx->SetMatchMode(SPH_MATCH_ANY);
		}

		$match = array();
		// Keep quotes
		$match[] = "#&quot;#";
		// KeepNew lines
		$match[] = "#[\n]+#";

		$replace = array('"', " ");

		$keywords = str_replace(array('&quot;', "\n"), array('"', ' '), trim($keywords));

		if (strlen($keywords) > 0)
		{
			$this->search_query = str_replace('"', '&quot;', $keywords);
			return true;
		}
		
		return false;
	}

	/**
	* Turns text into an array of words
	*/
	function split_message($text)
	{
		global $config;

		$this->get_ignore_words();

		// Split words
		$text = preg_replace('#([^\p{L}\p{N}\'*])#u', '$1$1', str_replace('\'\'', '\' \'', trim($text)));
		$matches = array();
		preg_match_all('#(?:[^\p{L}\p{N}*]|^)([+\-|]?(?:[\p{L}\p{N}*]+\'?)*[\p{L}\p{N}*])(?:[^\p{L}\p{N}*]|$)#u', $text, $matches);
		$text = $matches[1];

		if (sizeof($this->ignore_words))
		{
			$text = array_diff($text, $this->ignore_words);
		}

		// remove too short or too long words
		$text = array_values($text);
		for ($i = 0, $n = sizeof($text); $i < $n; $i++)
		{
			$text[$i] = trim($text[$i]);
			if (utf8_strlen($text[$i]) < $config['fulltext_sphinx_min_word_len'] || utf8_strlen($text[$i]) > $config['fulltext_sphinx_max_word_len'])
			{
				unset($text[$i]);
			}
		}

		return array_values($text);
	}

	/**
	* Performs a search on keywords depending on display specific params.
	*
	* @param array $id_ary passed by reference, to be filled with ids for the page specified by $start and $per_page, should be ordered
	* @param int $start indicates the first index of the page
	* @param int $per_page number of ids each page is supposed to contain
	* @return total number of results
	*/
	function keyword_search($type, &$fields, &$terms, &$sort_by_sql, &$sort_key, &$sort_dir, &$sort_days, &$ex_fid_ary, &$m_approve_fid_ary, &$topic_id, &$author_ary, &$id_ary, $start, $per_page)
	{
		global $config, $db, $auth;

		// No keywords? No posts.
		if (!strlen(trim($this->search_query)))
		{
			return false;
		}

		// generate a search_key from all the options to identify the results
		$search_key = md5(implode('#', array(
			$this->search_query,
			$type,
			$fields,
			$terms,
			$sort_days,
			$sort_key,
			$topic_id,
			implode(',', $ex_fid_ary),
			implode(',', $m_approve_fid_ary),
			implode(',', $author_ary)
		)));

		// try reading the results from cache
		$result_count = 0;
		if (false && $this->obtain_ids($search_key, $result_count, $id_ary, $start, $per_page, $sort_dir) == SEARCH_RESULT_IN_CACHE)
		{
			return $result_count;
		}

		$id_ary = array();

		$join_topic = ($type == 'posts') ? false : true;

		// sorting
		$sql_sort = $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');
		$sql_sort_table = $sql_sort_join = '';

		$this->sphinx->SetSortMode(($sort_dir == 'a') ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC, 'post_time');
		//$this->sphinx->SetSortMode(($sort_dir == 'a') ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC, 'post_subject_int');

		if (sizeof($ex_fid_ary))
		{
			// All forums that a user is allowed to access
			$fid_ary = array_unique(array_intersect(array_keys($auth->acl_getf('f_read', true)), array_keys($auth->acl_getf('f_search', true))));
			// All forums that the user wants to and can search in
			$search_forums = array_diff($fid_ary, $ex_fid_ary);
			
			if (sizeof($search_forums))
			{
				$this->sphinx->SetFilter('forum_id', $search_forums);
			}
		}

		if (sizeof($author_ary))
		{
			$this->sphinx->SetFilter('poster_id', $author_ary);
		}

		if ($type == 'topics')
		{
			$this->sphinx->SetGroupBy('topic_id', SPH_GROUPBY_ATTR);
		}

		if ($topic_id)
		{
			$this->sphinx->SetFilter('topic_id', array($topic_id));
		}
		
		switch($fields)
		{
			case 'titleonly':
				$this->sphinx->SetWeights(array(1,0)); // only weight for the title
				$this->sphinx->SetFilter('topic_first_post', array(1)); // 1 is first_post, 2 is not first post
				break;
			case 'msgonly':
				$this->sphinx->SetWeights(array(0,1)); // only weight for the body
				break;
			case 'firstpost':
				$this->sphinx->SetWeights(array(5,1)); // more relative weight for the title, also search the body
				$this->sphinx->SetFilter('topic_first_post', array(1)); // 1 is first_post, 2 is not first post
				break;
			default:
				$this->sphinx->SetWeights(array(5,1)); // more relative weight for the title, also search the body
				break;
		}

		$this->sphinx->SetLimits($start, (int) $config['search_block_size']);
		$result = $this->sphinx->Query($this->search_query);
		$id_ary = array();
		if (isset($result['matches']))
		{
			if ($type == 'posts')
			{
				$id_ary = array_keys($result['matches']);
			}
			else
			{
				foreach($result['matches'] as $key => $value)
				{
					$id_ary[] = $value['attrs']['topic_id'];
				}
			}
		}
		else
		{
			return false;
		}
		
		$result_count = $result['total_found'];

		// store the ids, from start on then delete anything that isn't on the current page because we only need ids for one page
		$this->save_ids($search_key, $this->search_query, $author_ary, $result_count, $id_ary, $start, $sort_dir);
		$id_ary = array_slice($id_ary, 0, (int) $per_page);

		return $result_count;
	}

	/**
	* Performs a search on an author's posts without caring about message contents. Depends on display specific params
	*
	* @param	string		$type				contains either posts or topics depending on what should be searched for
	* @param	boolean		$firstpost_only		if true, only topic starting posts will be considered
	* @param	array		&$sort_by_sql		contains SQL code for the ORDER BY part of a query
	* @param	string		&$sort_key			is the key of $sort_by_sql for the selected sorting
	* @param	string		&$sort_dir			is either a or d representing ASC and DESC
	* @param	string		&$sort_days			specifies the maximum amount of days a post may be old
	* @param	array		&$ex_fid_ary		specifies an array of forum ids which should not be searched
	* @param	array		&$m_approve_fid_ary	specifies an array of forum ids in which the searcher is allowed to view unapproved posts
	* @param	int			&$topic_id			is set to 0 or a topic id, if it is not 0 then only posts in this topic should be searched
	* @param	array		&$author_ary		an array of author ids
	* @param	array		&$id_ary			passed by reference, to be filled with ids for the page specified by $start and $per_page, should be ordered
	* @param	int			$start				indicates the first index of the page
	* @param	int			$per_page			number of ids each page is supposed to contain
	* @return	boolean|int						total number of results
	*
	* @access	public
	*/
	function author_search($type, $firstpost_only, &$sort_by_sql, &$sort_key, &$sort_dir, &$sort_days, &$ex_fid_ary, &$m_approve_fid_ary, &$topic_id, &$author_ary, &$id_ary, $start, $per_page)
	{
		global $config, $db;

		// No author? No posts.
		if (!sizeof($author_ary))
		{
			return 0;
		}

		// generate a search_key from all the options to identify the results
		$search_key = md5(implode('#', array(
			'',
			$type,
			($firstpost_only) ? 'firstpost' : '',
			'',
			'',
			$sort_days,
			$sort_key,
			$topic_id,
			implode(',', $ex_fid_ary),
			implode(',', $m_approve_fid_ary),
			implode(',', $author_ary)
		)));

		// try reading the results from cache
		$total_results = 0;
		if ($this->obtain_ids($search_key, $total_results, $id_ary, $start, $per_page, $sort_dir) == SEARCH_RESULT_IN_CACHE)
		{
			return $total_results;
		}

		$id_ary = array();

		// Create some display specific sql strings
		$sql_author		= $db->sql_in_set('p.poster_id', $author_ary);
		$sql_fora		= (sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('p.forum_id', $ex_fid_ary, true) : '';
		$sql_time		= ($sort_days) ? ' AND p.post_time >= ' . (time() - ($sort_days * 86400)) : '';
		$sql_topic_id	= ($topic_id) ? ' AND p.topic_id = ' . (int) $topic_id : '';
		$sql_firstpost = ($firstpost_only) ? ' AND p.post_id = t.topic_first_post_id' : '';

		// Build sql strings for sorting
		$sql_sort = $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');
		$sql_sort_table = $sql_sort_join = '';
		switch ($sql_sort[0])
		{
			case 'u':
				$sql_sort_table	= USERS_TABLE . ' u, ';
				$sql_sort_join	= ' AND u.user_id = p.poster_id ';
			break;

			case 't':
				$sql_sort_table	= ($type == 'posts') ? TOPICS_TABLE . ' t, ' : '';
				$sql_sort_join	= ($type == 'posts') ? ' AND t.topic_id = p.topic_id ' : '';
			break;

			case 'f':
				$sql_sort_table	= FORUMS_TABLE . ' f, ';
				$sql_sort_join	= ' AND f.forum_id = p.forum_id ';
			break;
		}

		if (!sizeof($m_approve_fid_ary))
		{
			$m_approve_fid_sql = ' AND p.post_approved = 1';
		}
		else if ($m_approve_fid_ary == array(-1))
		{
			$m_approve_fid_sql = '';
		}
		else
		{
			$m_approve_fid_sql = ' AND (p.post_approved = 1 OR ' . $db->sql_in_set('p.forum_id', $m_approve_fid_ary, true) . ')';
		}

		$select = ($type == 'posts') ? 'p.post_id' : 't.topic_id';
		$is_mysql = false;

		// If the cache was completely empty count the results
		if (!$total_results)
		{
			switch ($db->sql_layer)
			{
				case 'mysql4':
				case 'mysqli':
					$select = 'SQL_CALC_FOUND_ROWS ' . $select;
					$is_mysql = true;
				break;

				default:
					if ($type == 'posts')
					{
						$sql = 'SELECT COUNT(p.post_id) as total_results
							FROM ' . POSTS_TABLE . ' p' . (($firstpost_only) ? ', ' . TOPICS_TABLE . ' t ' : ' ') . "
							WHERE $sql_author
								$sql_topic_id
								$sql_firstpost
								$m_approve_fid_sql
								$sql_fora
								$sql_time";
					}
					else
					{
						if ($db->sql_layer == 'sqlite')
						{
							$sql = 'SELECT COUNT(topic_id) as total_results
								FROM (SELECT DISTINCT t.topic_id';
						}
						else
						{
							$sql = 'SELECT COUNT(DISTINCT t.topic_id) as total_results';
						}

						$sql .= ' FROM ' . TOPICS_TABLE . ' t, ' . POSTS_TABLE . " p
							WHERE $sql_author
								$sql_topic_id
								$sql_firstpost
								$m_approve_fid_sql
								$sql_fora
								AND t.topic_id = p.topic_id
								$sql_time" . (($db->sql_layer == 'sqlite') ? ')' : '');
					}
					$result = $db->sql_query($sql);

					$total_results = (int) $db->sql_fetchfield('total_results');
					$db->sql_freeresult($result);

					if (!$total_results)
					{
						return false;
					}
				break;
			}
		}

		// Build the query for really selecting the post_ids
		if ($type == 'posts')
		{
			$sql = "SELECT $select
				FROM " . $sql_sort_table . POSTS_TABLE . ' p' . (($topic_id || $firstpost_only) ? ', ' . TOPICS_TABLE . ' t' : '') . "
				WHERE $sql_author
					$sql_topic_id
					$sql_firstpost
					$m_approve_fid_sql
					$sql_fora
					$sql_sort_join
					$sql_time
				ORDER BY $sql_sort";
			$field = 'post_id';
		}
		else
		{
			$sql = "SELECT $select
				FROM " . $sql_sort_table . TOPICS_TABLE . ' t, ' . POSTS_TABLE . " p
				WHERE $sql_author
					$sql_topic_id
					$sql_firstpost
					$m_approve_fid_sql
					$sql_fora
					AND t.topic_id = p.topic_id
					$sql_sort_join
					$sql_time
				GROUP BY t.topic_id, " . $sort_by_sql[$sort_key] . '
				ORDER BY ' . $sql_sort;
			$field = 'topic_id';
		}

		// Only read one block of posts from the db and then cache it
		$result = $db->sql_query_limit($sql, $config['search_block_size'], $start);

		while ($row = $db->sql_fetchrow($result))
		{
			$id_ary[] = $row[$field];
		}
		$db->sql_freeresult($result);

		if (!$total_results && $is_mysql)
		{
			$sql = 'SELECT FOUND_ROWS() as total_results';
			$result = $db->sql_query($sql);
			$total_results = (int) $db->sql_fetchfield('total_results');
			$db->sql_freeresult($result);

			if (!$total_results)
			{
				return false;
			}
		}

		if (sizeof($id_ary))
		{
			$this->save_ids($search_key, '', $author_ary, $total_results, $id_ary, $start, $sort_dir);
			$id_ary = array_slice($id_ary, 0, $per_page);

			return $total_results;
		}
		return false;
	}

	/**
	 * Updates wordlist and wordmatch tables when a message is posted or changed
	 *
	 * @param string   $mode    Contains the post mode: edit, post, reply, quote
	 * @param int      $post_id The id of the post which is modified/created
	 * @param string   &$message   New or updated post content
	 * @param string   &$subject   New or updated post subject
	 * @param int      $poster_id  Post author's user id
	 * @param int      $forum_id   The id of the forum in which the post is located
	 *
	 * @access   public
	 */
	function index($mode, $post_id, &$message, &$subject, $poster_id, $forum_id)
	{
		global $db, $config;

		// Split old and new post/subject to obtain array of words
		$split_text = $this->split_message($message);
		$split_title = ($subject) ? $this->split_message($subject) : array();

		$words = array();
		if ($mode == 'edit')
		{
			$old_text = array();
			$old_title = array();

			$sql = 'SELECT post_text, post_subject
				FROM ' . POSTS_TABLE . "
				WHERE post_id = $post_id";
			$result = $db->sql_query($sql);

			if ($row = $db->sql_fetchrow($result))
			{
				$old_text = $this->split_message($row['post_text']);
				$old_title = $this->split_message($row['post_subject']);
			}
			$db->sql_freeresult($result);

			$words = array_unique(array_merge(
				array_diff($split_text, $old_text), 
				array_diff($split_title, $old_title),
				array_diff($old_text, $split_text),
				array_diff($old_title, $split_title)
			));
			unset($old_title);
			unset($old_text);
		}
		else
		{
			$words = array_unique(array_merge($split_text, $split_title));
		}
		unset($split_text);
		unset($split_title);

		// destroy cached search results containing any of the words removed or added
		$this->destroy_cache($words, array($poster_id));

		if ($this->index_created())
		{
			$rotate = (file_exists($config['fulltext_sphinx_data_path'] . 'searchd.pid')) ? ' --rotate' : '';
	
			$cwd = getcwd();
			chdir($config['fulltext_sphinx_bin_path']);
			exec('./' . INDEXER_NAME . $rotate . ' --config ' . $config['fulltext_sphinx_config_path'] . 'sphinx.conf index_phpbb_' . $this->id . '_delta > /dev/null 2>&1 &');
			chdir($cwd);
		}

		unset($words);
	}

	/**
	* Destroy cached results, that might be outdated after deleting a post
	*/
	function index_remove($post_ids, $author_ids, $forum_ids)
	{
		$this->destroy_cache(array(), $author_ids);
	}

	/**
	* Destroy old cache entries
	*/
	function tidy()
	{
		global $db, $config, $phpbb_root_path;

		// destroy too old cached search results
		$this->destroy_cache(array());

		if ($this->index_created())
		{
			$rotate = (file_exists($config['fulltext_sphinx_data_path'] . 'searchd.pid')) ? ' --rotate' : '';
	
			$cwd = getcwd();
			chdir($config['fulltext_sphinx_bin_path']);
			exec('./' . INDEXER_NAME . $rotate . ' --config ' . $config['fulltext_sphinx_config_path'] . 'sphinx.conf index_phpbb_' . $this->id . '_main > /dev/null 2>&1 &');
			exec('./' . INDEXER_NAME . $rotate . ' --config ' . $config['fulltext_sphinx_config_path'] . 'sphinx.conf index_phpbb_' . $this->id . '_delta > /dev/null 2>&1 &');
			chdir($cwd);
		}

		set_config('search_last_gc', time(), true);
	}

	/**
	* Create sphinx table
	*/
	function create_index($acp_module, $u_action)
	{
		global $db;

		$sql = 'CREATE TABLE ' . SPHINX_TABLE . ' (
			counter_id INT NOT NULL PRIMARY KEY,
			max_doc_id INT NOT NULL
		)';

		// return false if it was successful
		$db->sql_query($sql);

		$this->tidy();
		return false;
	}

	/**
	* Drop sphinx table
	*/
	function delete_index($acp_module, $u_action)
	{
		global $db;
		$sql = 'DROP TABLE ' . SPHINX_TABLE;

		//return false if it succeeded
		return !$db->sql_query($sql);
	}

	/**
	* Returns true if the sphinx table was created
	*/
	function index_created()
	{
		global $db;

		$sql = 'SHOW TABLES LIKE \'' . SPHINX_TABLE . '\'';
		$result = $db->sql_query($sql);

		if ($db->sql_fetchrow($result))
		{
			return true;
		}

		return false;
	}

	/**
	* Returns an associative array containing information about the indexes
	*/
	function index_stats()
	{
		global $user;

		if (empty($this->stats))
		{
			$this->get_stats();
		}

		$user->add_lang('mods/fulltext_sphinx');

		return array(
			$user->lang['FULLTEXT_SPHINX_MAIN_POSTS']			=> ($this->index_created()) ? $this->stats['main_posts'] : 0,
			$user->lang['FULLTEXT_SPHINX_DELTA_POSTS']			=> ($this->index_created()) ? $this->stats['total_posts'] - $this->stats['main_posts'] : 0,
			$user->lang['FULLTEXT_MYSQL_TOTAL_POSTS']			=> ($this->index_created()) ? $this->stats['total_posts'] : 0,
		);
	}

	function get_stats()
	{
		global $db;

		$sql = 'SELECT COUNT(post_id) as total_posts
			FROM ' . POSTS_TABLE;
		$result = $db->sql_query($sql);
		$this->stats['total_posts'] = (int) $db->sql_fetchfield('total_posts');
		$db->sql_freeresult($result);

		if ($this->index_created())
		{
			$sql = 'SELECT COUNT(p.post_id) as main_posts
				FROM ' . POSTS_TABLE . ' p, ' . SPHINX_TABLE . ' m
				WHERE p.post_id <= m.max_doc_id
					AND m.counter_id = 1';
			$result = $db->sql_query($sql);
			$this->stats['main_posts'] = (int) $db->sql_fetchfield('main_posts');
			$db->sql_freeresult($result);
		}
	}

	/**
	* Returns a list of options for the ACP to display
	*/
	function acp()
	{
		global $user, $config;

		$user->add_lang('mods/fulltext_sphinx');

		$config_vars = array(
			'fulltext_sphinx_config_path' => 'string',
			'fulltext_sphinx_data_path' => 'string',
			'fulltext_sphinx_bin_path' => 'string',
			'fulltext_sphinx_port' => 'int',
		);

		foreach ($config_vars as $config_var => $type)
		{
			if (!isset($config[$config_var]))
			{
				set_config($config_var, '');
			}
		}

		$bin_path = $config['fulltext_sphinx_bin_path'];

		// try to guess the path if it is empty
		if (empty($bin_path))
		{
			if (file_exists('/usr/local/bin/' . INDEXER_NAME) && file_exists('/usr/local/bin/' . SEARCHD_NAME))
			{
				$bin_path = '/usr/local/bin/';
			}
			else if (file_exists('/usr/bin/' . INDEXER_NAME) && file_exists('/usr/bin/' . SEARCHD_NAME))
			{
				$bin_path = '/usr/local/bin/';
			}
			else
			{
				$output = array();
				if (!@exec('whereis indexer', $output))
				{
					return array(
						'tpl' => $user->lang['FULLTEXT_SPHINX_REQUIRES_EXEC'],
						'config' => array()
					);
				}
				if (sizeof($output))
				{
					$output = explode(' ', $output[0]);
					array_shift($output); // remove indexer:
					foreach ($output as $path)
					{
						$path = dirname($path) . '/';
						if (file_exists($path . INDEXER_NAME) && file_exists($path . SEARCHD_NAME))
						{
							$bin_path = $path;
							break;
						}
					}
				}
			}
		}

		$tpl = '
		<dl>
			<dt><label for="fulltext_sphinx_config_path">' . $user->lang['FULLTEXT_SPHINX_CONFIG_PATH'] . ':</label><br /><span>' . $user->lang['FULLTEXT_SPHINX_CONFIG_PATH_EXPLAIN'] . '</span></dt>
			<dd><input id="fulltext_sphinx_config_path" type="text" size="40" maxlength="255" name="config[fulltext_sphinx_config_path]" value="' . $config['fulltext_sphinx_config_path'] . '" /></dd>
		</dl>
		<dl>
			<dt><label for="fulltext_sphinx_data_path">' . $user->lang['FULLTEXT_SPHINX_DATA_PATH'] . ':</label><br /><span>' . $user->lang['FULLTEXT_SPHINX_DATA_PATH_EXPLAIN'] . '</span></dt>
			<dd><input id="fulltext_sphinx_data_path" type="text" size="40" maxlength="255" name="config[fulltext_sphinx_data_path]" value="' . $config['fulltext_sphinx_data_path'] . '" /></dd>
		</dl>
		<dl>
			<dt><label for="fulltext_sphinx_bin_path">' . $user->lang['FULLTEXT_SPHINX_BIN_PATH'] . ':</label><br /><span>' . $user->lang['FULLTEXT_SPHINX_BIN_PATH_EXPLAIN'] . '</span></dt>
			<dd><input id="fulltext_sphinx_bin_path" type="text" size="40" maxlength="255" name="config[fulltext_sphinx_bin_path]" value="' . $bin_path . '" /></dd>
		</dl>
		<dl>
			<dt><label for="fulltext_sphinx_port">' . $user->lang['FULLTEXT_SPHINX_PORT'] . ':</label><br /><span>' . $user->lang['FULLTEXT_SPHINX_PORT_EXPLAIN'] . '</span></dt>
			<dd><input id="fulltext_sphinx_port" type="text" size="4" maxlength="10" name="config[fulltext_sphinx_port]" value="' . $config['fulltext_sphinx_port'] . '" /></dd>
		</dl>
		';

		// These are fields required in the config table
		return array(
			'tpl'		=> $tpl,
			'config'	=> $config_vars
		);
	}
}

?>