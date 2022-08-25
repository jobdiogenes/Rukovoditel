<?php
$data = array(
//zero value
		'nul' => 'zero',
		//form 1-9
		'ten' =>
		array(
				array('','um', 'dois', 'tres', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'),
				array('','um', 'dois', 'tres', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'),
		),
		//from 10-19
		'a20' =>
		array('dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'),
		//from 20-90
		'tens' =>
		array(2=>'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'),
		//from 100-900
		'hundred' =>
		array('','cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'),
		//units
		'unit' =>
		array( // Units
				array('centavo', 'centavos', 'centavos',	 1),
				array('real'   ,'reais'   ,'reais'    ,0),
				array('mil', 'milhares', 'milhares'     ,1),
				array('millhão' ,'milhões','milhões' ,0),
				array('bilhão', 'bilhões', 'bilhões',0),
		)
);