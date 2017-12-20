<?php
	error_reporting(E_ALL);
	ini_set('display_errors',1);
	include 'HTML_Template.php';

	$DT = array(
		'title' => 'Cities',
		'city' => array(
			array( 'name' => 'Moscow',          'count' => 12197596, 'foundation' => 1147 ),
			array( 'name' => 'Saint Petersburg','count' =>  5191690, 'foundation' => 1703 ),
			array( 'name' => 'Novosibirsk',     'count' =>  1567087, 'foundation' => 1893 ),
			array( 'name' => 'Ekaterinburg',    'count' =>  1428042, 'foundation' => 1723 ),
			array( 'name' => 'Nizhny Novgorod', 'count' =>  1267760, 'foundation' => 1221 ),
			array( 'name' => 'Kazan',           'count' =>  1205651, 'foundation' => 1005 ),
			array( 'name' => 'Chelyabinsk',     'count' =>  1183387, 'foundation' => 1736 ),
			array( 'name' => 'Omsk',            'count' =>  1173854, 'foundation' => 1716 ),
			array( 'name' => 'Samara',          'count' =>  1171820, 'foundation' => 1586 ),
			array( 'name' => 'Rostov-on-Don',   'count' =>  1114806, 'foundation' => 1749 ),
		),
		'sel' => 5,
		'action' => 'button',
	);
	//echo '<pre>'.print_r($DT,1).'</pre>';
	try {
		$tpl = new HTML_Template(__DIR__.'/tmpl');
		$tpl->loadTemplatefile('index.tpl.html');
		$tpl->setVariable($DT);
		//echo '<pre>'.htmlspecialchars(print_r($tpl,1)).'</pre>';
		$tpl->show();
	} catch (Exception $e) {
		die($e->getMessage());
	}
