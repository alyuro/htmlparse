<?php
#=============================
#
#	Author: ALYURO
#	Creation: 2017/09/15
#	Version: 0.5 beta
#
#=============================

class HTML_Template 
{
	private
		$vartag = '#\{([A-Za-z][^}]*)\}#', // {var}, {arr.var}, {que ? 'val'}, {que ? var1 : var2}, ...
		$blocktag = '#<!--\s*([A-Z\/][A-Z]+)\s*(.*?)\s*-->#', // <!--TAG info--> + <!--/TAG-->
		$blockerr = '<!--#', // mark for skipped blocks
		$childMark = '{#HTML_BLOCK_nn#}', // inner tag for template blocks markup
		$blockFunc = array(),
		$blockClose = array(),
		$blockArray = array(),
		$blockLinks = array(),
		$variable  = array(),
		$options = array();

	protected
		$currentBlock = 0,
		$template_dir = './',
		$template = '',
		$open = '\s+(\w+)',
		$close_tag = '\s+(\w+)',
		$html;

//= private ==============================================================

	private function addBlockType($tag_open,$tag_close,$callback)
	{
		if ($tag_close) $this->blockClose[$tag_close] = $tag_open;
		$this->blockFunc[$tag_open] = $callback;
	}

	private function splitBlocks($html,$n=0)
	{
		$numStack[] = $n;
		$this->blockArray[$n] = array(
			'type' => 'BEGIN',
			'info' => $n ? "include_$n" : '__global__',
			'tmpl' => '',
			'html' => array()
		);

		while (1)
		{
			$buf = preg_split($this->blocktag, $html, 2, PREG_SPLIT_DELIM_CAPTURE);
			@list($current_block, $type, $info, $next_block) = array_values($buf);
			if (!$type) break;

			if (@$this->blockFunc[$type]) { // continue current block, push new one
				$n = end($numStack);
				$this->blockArray[$n]['tmpl'] .= $current_block;
				$this->blockArray[] = array( 'type'=>$type, 'info'=>trim($info), 'tmpl'=>'', 'html' => array() );
				$nn = count($this->blockArray)-1;
				if (in_array($type,$this->blockClose)) array_push($numStack, $nn); // push only if block has it's body
				$this->blockLinks[trim($info)] = $nn; // direct link to block by name
				$this->blockArray[$n]['tmpl'] .= str_replace('nn',$nn,$this->childMark);
			} elseif (@$this->blockClose[$type]) { // pop last block, continue previous one
				$n = array_pop($numStack);
				if ($this->blockClose[$type]!=$this->blockArray[$n]['type']) {
					$err = $this->blockArray[$n];
					throw new Exception("HTML_Template Error: block [$err[type] $err[info]] can`t be closed by [$type] tag");
				}
				$this->blockArray[$n]['tmpl'] .= $current_block;
			} else { // do nothing, mark block as incorrect

			}
			$html = $next_block;
		}
		$n = array_pop($numStack);
		if (count($numStack)!=0) {
			$err = $this->blockArray[$n];
			throw new Exception("HTML_Template Error: block [$err[type] $err[info]] not ended properly");
		}
		$this->blockArray[$n]['tmpl'] .= $html;
	}

	protected function getVarName($name)
	{
		return '$this->variable["'.str_replace('.','"]["',is_array($name)?reset($name):$name).'"]';
	}

	protected function parseVar($str)
	{
		$info = $str;
		$quot = false;
		$if = false;
		$ret = '';
		$pos = -1;
		$aVar = array();
		for ($n=0; $n<strlen($str); $n++)
		{	$chr = $str[$n];
			if (!$quot&&($chr=='"'||$chr=="'")) { $quot = $chr; $pos = -1; }// open quot mode
			elseif ($quot===$chr) $quot = false;	// close quot mode
			elseif ($quot&&$chr=="\\") $n++; // skip slashed quot
			elseif ($quot) continue; // skip parse in quot mode
			elseif (!$if&&$chr=='?'||$if&&$chr==':') { $if = !$if; $pos = -1; }
			elseif ($pos<0  && preg_match('/[a-z]/i',$chr)) $aVar[$pos=$n] = $chr;
			elseif ($pos>=0 && preg_match('/[\w\.]/',$chr)) $aVar[$pos] .= $chr;
			else $pos = -1; }
		$aVar = array_reverse($aVar,1);
		if ($if) $str .= ':""';
		foreach ($aVar as $pos=>&$v) {
			if (substr($v,-1)=='.') $v = substr($v,0,strlen($v)-1); // can't end with dot
			$str = substr($str,0,$pos).$this->getVarName($v).substr($str,$pos+strlen($v));
		}
		eval("@\$ret = $str;");
		$e = error_get_last();
		if ($e['type']==4) {
			throw new Exception("HTML_Template Error: {{$info}} statement processing error");
		}
		if (!$this->options['save_vars']) foreach ($aVar as $v) eval("unset(".$this->getVarName($v).");");
		return $ret;
	}

	protected function parse_block($n=0)
	{
		$parsed = @$this->blockArray[$n]['touch'] || $this->options['save_empty'] ? true : false;
		unset($this->blockArray[$n]['touch']);

		$tmpl = $this->blockArray[$n]['tmpl'];
		//- parse inner blocks ---
		preg_match_all('/\{#HTML_BLOCK_(\d+)#\}/', $tmpl, $subs);
		foreach ($subs[1] as $sub) {
			if ($tsub = $this->get($sub)) $parsed = true;
			$tmpl = str_replace("{#HTML_BLOCK_$sub#}", $tsub, $tmpl);
		}
		//- parse variables ---
		preg_match_all($this->vartag, $tmpl, $vars);
		foreach ($vars[0] as $num=>$var) {
			$val = @$this->parseVar($vars[1][$num]);
			$tmpl = str_replace($var, $val, $tmpl);
			if (!$val) continue;
			$parsed = true;
		}
		if ($parsed) $this->blockArray[$n]['html'][] = $tmpl;
		return $this->blockArray[$n]['html'];
	}

	protected function parse_include($n)
	{
		$fname = $this->blockArray[$n]['info'];
		if (!file_exists($this->template_dir.$fname))
		{	$fname = $this->parseVar($fname); }
		$html = file_get_contents($this->template_dir.$fname);
		$this->splitBlocks($html,$n);
		return $this->parse_block($n);
	}

	protected function parse_for($n)
	{
		preg_match('/(\S+)(?:\s+AS\s+(\S+))?(?:\s+KEY\s+(\S+))?/',$this->blockArray[$n]['info'],$buf);
		@list(,$arr,$var,$key) = $buf;
		$cycle = $this->parseVar($arr);
		if (!is_array($cycle)) return false;
		foreach ($cycle as $i=>$item) {
			if ($key) $this->variable[$key] = $i;
			if ($var) $this->variable[$var] = $item;
			$this->parse_block($n);
		}
		if ($key) unset($this->variable[$key]);
		if ($var) unset($this->variable[$var]);
		return $this->blockArray[$n]['html'];
	}

	protected function parse_if($n)
	{
		$ret = $this->parseVar($this->blockArray[$n]['info']);
		if (!$ret) return array();
		$this->blockArray[$n]['touch'] = true; // is it necessary?
		return $this->parse_block($n);
	}

	protected function parse_set($n)
	{
		$this->parseVar($this->blockArray[$n]['info']);
		return array();
	}

	protected function parse_vars($n)
	{
		return array(print_r($this->variable,1));
	}
//= public ===============================================================

	public function __construct($dir)
	{
		$this->template_dir = (substr($dir,-1)=='/') ? $dir : "$dir/";
		$this->addBlockType('BEGIN','END','parse_block');
		$this->addBlockType('INCLUDE',null,'parse_include');

		$this->addBlockType('BLOCK','/BLOCK','parse_block');
		$this->addBlockType('FOR','/FOR','parse_for');
		$this->addBlockType('IF','/IF','parse_if');
		$this->addBlockType('SET',null,'parse_set');
		$this->addBlockType('VARS',null,'parse_vars');
	}

	public function setOption($option, $value)
	{
		$this->options[$option] = $value;
	}


	public function loadTemplatefile( $filename, $saveUsedVariables = true, $saveEmptyBlocks = true )
	{
		$html = file_get_contents($this->template_dir.$filename);
		$this->splitBlocks($html);
		$this->options = array(
			'save_vars'  => $saveUsedVariables ? true : false,
			'save_empty' => $saveEmptyBlocks   ? true : false,
			'init_includes' => false,
		);
	}

	public function setVariable($variable, $value=null)
	{
		if (is_array($variable)) {
			foreach ($variable as $k=>$v)
				$this->variable[$k] = $v;
		} elseif (isset($value)) {
			$this->variable[$variable] = $value;
		}
		return true;
	}

	public function touchBlock($block)
	{
		$n = is_numeric($block) ? $block : $this->blockLinks[$block];
		$this->blockArray[$n]['touch'] = true;
		return $n>0;
	}

	public function parseCurrentBlock()
	{
		return $this->parse($this->currentBlock);
	}

	public function parse($block = 0, $flag_recursion = false)
	{
		$n = is_numeric($block) ? $block : $this->blockLinks[$block];
		$func = $this->blockFunc[ $this->blockArray[$n]['type'] ];
		return $this->$func($n);
	}

	public function get($block=0)
	{
		$n = is_numeric($block) ? $block : $this->blockLinks[$block];
		$ret = $this->parse($n);
		$ret = implode('',$ret);
		$this->blockArray[$n]['html'] = array(); // empty for new parse
		return $ret;
	}

	public function show($block=0)
	{
		echo $this->get();
	}

}
