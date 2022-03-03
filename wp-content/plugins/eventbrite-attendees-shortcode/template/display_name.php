<?php if ( !empty( $value ) ) : global $attendee_website; ?>
	<?php $make_clickable = apply_filters( "eventbrite_attendees_{$name}_make_clickable", false ); ?>
	<li class="eb-attendee-list-item eb-<?php echo sanitize_html_class( $name ); ?>">
	<?php $value = !empty( $attendee_website ) && $make_clickable ? '<a href="' . esc_url( $attendee_website ) . '">' . esc_attr( $value ) . '</a>' : esc_attr( $value ); ?>
	<?php echo isset( $clickable ) && $clickable ? make_clickable( $value ) : $value; ?>
	</li>
<?php endif; ?>