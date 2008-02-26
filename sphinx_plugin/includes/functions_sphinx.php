<?php
/** 
*
* @package search
* @version $Id$
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
* fulltext_mysql
* Fulltext search for MySQL
* @package search
*/
class sphinx_config
{
	var $loaded = false;
	var $sections = array();

	function sphinx_config($filename = false)
	{
		if ($filename !== false && file_exists($filename))
		{
			$this->read($filename);
		}
	}

	function &get_section_by_name($name)
	{
		for ($i = 0, $n = sizeof($this->sections); $i < $n; $i++)
		{
			if (is_a($this->sections[$i], 'sphinx_config_section') && $this->sections[$i]->get_name() == $name)
			{
				return $this->sections[$i];
			}
		}
		$null = null;
		return $null;
	}

	function &add_section($name)
	{
		$this->sections[] = new sphinx_config_section($name, '');
		return $this->sections[sizeof($this->sections) - 1];
	}

	function read($filename)
	{
		$config_file = file($filename);

		$this->sections = array();

		$section = null;
		$found_opening_bracket = false;
		$in_value = false;

		foreach ($config_file as $i => $line)
		{
			if ($in_value)
			{
				$line = rtrim($line);
			}
			else
			{
				$line = trim($line);
			}

			if (!$section)
			{
				if (!$line || $line[0] == '#')
				{
					$this->sections[] = new sphinx_config_comment($config_file[$i]);
					continue;
				}
				else
				{
					$section_name = '';
					$section_name_comment = '';
					$found_opening_bracket = false;
					for ($j = 0, $n = strlen($line); $j < $n; $j++)
					{
						if ($line[$j] == '#')
						{
							$section_name_comment = substr($line, $j);
							break;
						}

						if ($found_opening_bracket)
						{
							continue;
						}

						if ($line[$j] == '{')
						{
							$found_opening_bracket = true;
							continue;
						}

						$section_name .= $line[$j];
					}

					$section_name = trim($section_name);
					$section = new sphinx_config_section($section_name, $section_name_comment);
				}
			}
			else
			{
				$skip_first = false;
				if (!$in_value)
				{
					if (!$line || $line[0] == '#')
					{
						$section->add_variable(new sphinx_config_comment($config_file[$i]));
						continue;
					}
	
					if (!$found_opening_bracket)
					{
						if ($line[0] == '{')
						{
							$skip_first = true;
							$line = substr($line, 1);
							$found_opening_bracket = true;
						}
						else
						{
							$section->add_variable(new sphinx_config_comment($config_file[$i]));
							continue;
						}
					}
				}

				if ($line || $in_value)
				{
					if (!$in_value)
					{
						$name = '';
						$value = '';
						$comment = '';
						$found_assignment = false;
					}
					$in_value = false;
					$end_section = false;

					for ($j = 0, $n = strlen($line); $j < $n; $j++)
					{
						if ($line[$j] == '#')
						{
							$comment = substr($line, $j);
							break;
						}
						else if ($line[$j] == '}')
						{
							$comment = substr($line, $j + 1);
							$end_section = true;
							break;
						}
						else if (!$found_assignment)
						{
							if ($line[$j] == '=')
							{
								$found_assignment = true;
							}
							else
							{
								$name .= $line[$j];
							}
						}
						else
						{
							if ($line[$j] == '\\' && $j == $n - 1)
							{
								$value .= "\n";
								$in_value = true;
								continue 2; // go to the next line and keep processing the value in there
							}
							$value .= $line[$j];
						}
					}

					if ($name && $found_assignment)
					{
						$section->add_variable(new sphinx_config_variable(trim($name), trim($value), ($end_section) ? '' : $comment));
						continue;
					}

					if ($end_section)
					{
						$section->set_end_comment($comment);
						$this->sections[] = $section;
						$section = null;
						continue;
					}
				}

				$comment = ($skip_first) ? "\t" . substr(ltrim($config_file[$i]), 1) : $config_file[$i];
				$section->add_variable(new sphinx_config_comment($comment));
			}
		}

		$this->loaded = $filename;
	}

	function write($filename = false)
	{
		if ($filename === false && $this->loaded)
		{
			$filename = $this->loaded;
		}

		$data = "";
		foreach ($this->sections as $section)
		{
			$data .= $section->to_string();
		}

		$fp = fopen($filename, 'wb');
		fwrite($fp, $data);
		fclose($fp);
	}
}

class sphinx_config_section
{
	var $name;
	var $comment;
	var $end_comment;
	var $variables = array();

	function sphinx_config_section($name, $comment)
	{
		$this->name = $name;
		$this->comment = $comment;
		$this->end_comment = '';
	}

	function add_variable($variable)
	{
		$this->variables[] = $variable;
	}

	function set_end_comment($end_comment)
	{
		$this->end_comment = $end_comment;
	}

	function get_name()
	{
		return $this->name;
	}

	function &get_variable_by_name($name)
	{
		for ($i = 0, $n = sizeof($this->variables); $i < $n; $i++)
		{
			if (is_a($this->variables[$i], 'sphinx_config_variable') && $this->variables[$i]->get_name() == $name)
			{
				return $this->variables[$i];
			}
		}
		$null = null;
		return $null;
	}

	function delete_variables_by_name($name)
	{
		for ($i = 0; $i < sizeof($this->variables); $i++)
		{
			if (is_a($this->variables[$i], 'sphinx_config_variable') && $this->variables[$i]->get_name() == $name)
			{
				array_splice($this->variables, $i, 1);
				$i--;
			}
		}
	}

	function &create_variable($name, $value)
	{
		$this->variables[] = new sphinx_config_variable($name, $value, '');
		return $this->variables[sizeof($this->variables) - 1];
	}

	function to_string()
	{
		$content = $this->name . " " . $this->comment . "\n{\n";

		while (trim($this->variables[0]->to_string()) == "")
		{
			array_shift($this->variables);
		}

		foreach ($this->variables as $variable)
		{
			$content .= $variable->to_string();
		}
		$content .= '}' . $this->end_comment . "\n";

		return $content;
	}
}

class sphinx_config_variable
{
	var $name;
	var $value;
	var $comment;

	function sphinx_config_variable($name, $value, $comment)
	{
		$this->name = $name;
		$this->value = $value;
		$this->comment = $comment;
	}

	function get_name()
	{
		return $this->name;
	}

	function set_value($value)
	{
		$this->value = $value;
	}

	function to_string()
	{
		return "\t" . $this->name . ' = ' . str_replace("\n", "\\\n", $this->value) . ' ' . $this->comment . "\n";
	}
}

class sphinx_config_comment
{
	var $exact_string;

	function sphinx_config_comment($exact_string)
	{
		$this->exact_string = $exact_string;
	}

	function strip_exact_string($chars)
	{
		$exact_string = substr($exact_string, $chars);
	}

	function to_string()
	{
		return $this->exact_string;
	}
}

?>