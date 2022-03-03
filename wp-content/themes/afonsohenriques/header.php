<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
	<title><?php wp_title('&laquo;', true, 'right'); ?> <?php bloginfo('name'); ?></title>

	<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" type="text/css" media="screen" />
	<!--[if lt IE 7]><link rel="stylesheet" href="<?php bloginfo('template_directory'); ?>/assets/css/ie.css" type="text/css" media="screen" /><![endif]-->
	
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	
<!--[if lt IE 7]>
<div id="ltie7-wrap">
<div id="ltie7">
	<h3>Est&aacute; a utilizar uma vers&atilde;o desactualizada do Internet Explorer</h3>
	<p>Para manter uma navega&ccedil;&atilde;o segura na internet recomendamos que actualize o seu browser para a vers&atilde;o mais recente ou que procure um browser alternativo, como o <a href="http://getfirefox.com" target="_blank" title="visitar o website do browser Firefox">Firefox</a>. Visite a p&aacute;gina da iniciativa <a href="http://browsehappy.com/" target="_blank">Browse Happy</a> (website em ingl&ecirc;s), para conhecer as alternativas ao Internet Explorer.</p>
</div>
</div>
<![endif]--> <!-- IE upgrade info -->
		
<div id="body-wrap">

	<div id="header">
		<div id="logo"><h1><a href="<?php bloginfo('url'); ?>/"><?php get_bloginfo('name'); ?></a></h1></div><!-- #logo -->
	</div> <!-- #header -->

	<div id="content">