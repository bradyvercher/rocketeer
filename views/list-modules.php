<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e( 'Jetpack Modules', 'rocketeer' ); ?></h2>

	<?php do_action( 'rocketeer_notices' ); ?>

	<?php $modules_list_table->views(); ?>

	<form action="<?php echo add_query_arg( array( 'redirect_to' => 'rocketeer' ) ); ?>" method="post">
		<?php $modules_list_table->display(); ?>
	</form>
</div>
