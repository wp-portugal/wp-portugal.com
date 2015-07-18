<?php 
/** 
 * Template Name: CSV page
 */
 

get_header(); ?>

<div class="row">
	<div class="small-12 large-8 columns" role="main">

	<?php do_action( 'foundationpress_before_content' ); ?>

	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class() ?> id="post-<?php the_ID(); ?>">
			<header>
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header>
			<?php do_action( 'foundationpress_page_before_entry_content' ); ?>
			<div class="entry-content">
				<?php the_content(); ?>
				
			<div id="gloss__block"><h2>Glossário</h2>
			<form id     = "booking__form"
  		class  = "form-horizontal"  
        method = "get"  >
				<label class="floatl" for="booking__name<?= $i ?>"><?php _e('Termo em Inglês','43lc');?></label>
				<input type        ="text"  required
				       class       ="form-control " 
				       id          ="term__text" 
				       name        ="term__text"
				       value			="<?= $term__text?>"
				       min        ="0"
				       placeholder ="<?php _e('termo*','43lc');?>">
				       
			
			</form>
			<h3>Resultado:</h3>
			<p id="term__result"></p>
			
			</div>
			<div id="latestterms">
				<p id="firstresult" style="display: none;"></p>
				
			</div>
			

			</div>
			<footer>
				<?php wp_link_pages( array('before' => '<nav id="page-nav"><p>' . __( 'Pages:', 'foundationpress' ), 'after' => '</p></nav>' ) ); ?>
				<p><?php the_tags(); ?></p>
			</footer>
			<?php do_action( 'foundationpress_page_before_comments' ); ?>
			<?php comments_template(); ?>
			<?php do_action( 'foundationpress_page_after_comments' ); ?>
		</article>
	<?php endwhile;?>

	<?php do_action( 'foundationpress_after_content' ); ?>

	</div>
	<?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>

