<?php if ( !empty( $value ) ) : ?>
	<li class="eb-attendee-list-item eb-<?php echo sanitize_html_class( $name ); ?>">
	<?php esc_attr_e( $value ); ?>&nbsp;
	</li>
<?php endif; ?>