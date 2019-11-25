<?php

/**
 * Render the points block in the cart
 */

defined('ABSPATH') || exit;
global $wc_points_rewards_handler;
$points = $wc_points_rewards_handler->points_used_earned_in_cart();
?>
<tr class="woocommerce-points-totals">
    <th colspan="2"><strong><?php echo $wc_points_rewards_handler->points_label(); ?></strong></th>
    <td colspan="2" style="text-align: right;">
        <?php if (is_user_logged_in()) : ?>
            <strong>
                <span class="woocommerce-points-totals_used">
                    <?php echo $wc_points_rewards_handler->points_used_label() . '&nbsp;' . $points['used']; ?>
                </span>
                <span class="woocommerce-points-totals_separator">&nbsp;/&nbsp;</span>
                <span class="woocommerce-points-totals_earned">
                    <?php echo $wc_points_rewards_handler->points_earned_label() . '&nbsp;' . $points['earned']; ?>
                </span>
            </strong>
        <?php else : ?>
            <a href="<?php echo wp_login_url( wc_get_cart_url() ); ?>" class="woocommerce-points-totals_login" rel="nofollow">
                <?php echo $wc_points_rewards_handler->points_get_cart_login_text(); ?>
            </a>
        <?php endif; ?>
    </td>
</tr>