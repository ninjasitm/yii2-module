<?php

namespace nitm\models\configer\formats;

use Yii;
use nitm\helpers\Directory;

/**
 * Text fiale parser for configer
 */
class PlainFile extends BaseFileFormat
{	
	public function prepare(array $data)
	{
		array_multisort($data, SORT_ASC);
		if(count($data) > 0)
		{
			foreach ($data as $key => $item)
			{
				if (is_array($item))
				{
					$sections .= "\n[{$key}]\n";
					foreach ($item as $key2 => $item2)
					{
						if (is_numeric($item2) || is_bool($item2))
						{
							$sections .= "{$key2} = {$item2}\n";
						}
						else
						{
							$sections .= "{$key2} = {$item2}\n";
						}
					}     
				}
				else
				{
					if(is_numeric($item) || is_bool($item))
					{
						$this->contents .= "{$key} = {$item}\n";
					}
					else
					{
						$this->contents .= "{$key} = {$item}\n";
					}
				}
			}
			$this->contents .= $sections;
		}
	}
	 
	public function read($contents)
	{
		switch(!empty($this->contents))
		{
			case true:
			$section = '';
			$this->contents = array_filter((is_null($this->contents)) ? $contents : $this->contents);
			if(is_array($this->contents) && (sizeof($this->contents) > 0))
			{
				foreach($this->contents as $filedata) 
				{
					$dataline = trim($filedata);
					$firstchar = substr($dataline, 0, 1);
					if($firstchar!= $commentchar && $dataline != '') 
					{
						//It's an entry (not a comment and not a blank line)
						if($firstchar == '[' && substr($dataline, -1, 1) == ']') 
						{
							//It's a section
							$section = substr($dataline, 1, -1);
							$this->config['current']['sections'][$section] = $section;
							$ret_val[$section] = [];
						}
						else
						{
							$model = new Value(["value" => $value, "comment" => @$comment, 'sectionid' => $section, "name" => $key, "unique_name" => "$section.$key"]);
							//It's a key...
							$delimiter = strpos($dataline, '=');
							if($delimiter > 0) 
							{
								//...with a value
								$key = trim(substr($dataline, 0, $delimiter));
								$value = trim(substr($dataline, $delimiter + 1));
								if(substr($value, 1, 1) == '"' && substr($value, -1, 1) == '"') 
								{ 
									$value = substr($value, 1, -1); 
								}
								$model->value = $value;
								//$ret_val[$section][$key] = stripslashes($value);
								//we may return comments if we're updating
								$ret_val[$section][$key] = $model;
							}
							else
							{
								//we may return comments if we're updating
								//...without a value
								$ret_val[$section][trim($dataline)] = $model;
							}
						}
					}
				}
			}
			break;
		}
	}
	
	public function createSection($name)
	{
		$args['command'] = "sed -i '\$a\\\n\\n[%s]' ";
		return $this->command($command, [$name]);
	}
	
	public function createValue($section, $name, $value)
	{
		$args['command'] = "sed -i '/\[%s\]/a %s = %s' ";
		return $this->command($command, [$section, $name, $value]);
	}
	
	public function updateSection($section, $name)
	{
		$args['command'] = 'sed -i -e "s/^\[%s\]/%s/" ';
		return $this->command($command, [$section, $name]);	
	}
	
	public function updateValue($section, $name, $value)
	{
		$args['command'] = 'sed -i -e "/^\[%s\]/,/^$/{s/%s =.*/%s = %s/}" ';
		return $this->command($command, [$section, $name, $name, $value]);	
	}
	
	public function deleteSection($section, $name)
	{
		$args['command'] = "sed -i '/^\[%s\]/,/^$/d' ";
		return $this->command($command, [$section, $name]);	
	}
	
	public function deleteValue($section, $name, $value)
	{
		$args['command'] = "sed -i '/^\[%s\]/,/^$/{/^%s =.*/d}' ";
		return $this->command($command, [$section, $name, $name, $value]);	
	}
}
