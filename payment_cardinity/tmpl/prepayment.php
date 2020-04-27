<?php

/**
 * @package J2Store
 * @copyright Copyright (c) 2018 cardinity.com
 * @license GNU GPL v3 or later
 */
/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');
?>
<form name="checkout" method="POST" action="https://checkout.cardinity.com">
    <button type=submit>Click Here</button>
    <input type="hidden" name="amount" value="<?php echo $vars->attributes['amount'] ?>" />
    <input type="hidden" name="cancel_url" value="<?php echo $vars->attributes['cancel_url'] ?>" />
    <input type="hidden" name="country" value="<?php echo $vars->attributes['country'] ?>" />
    <input type="hidden" name="currency" value="<?php echo $vars->attributes['currency'] ?>" />
    <input type="hidden" name="description" value="<?php echo $vars->attributes['description'] ?>" />
    <input type="hidden" name="order_id" value="<?php echo $vars->attributes['order_id'] ?>" />
    <input type="hidden" name="project_id" value="<?php echo $vars->attributes['project_id'] ?>" />
    <input type="hidden" name="return_url" value="<?php echo $vars->attributes['return_url'] ?>" />
    <input type="hidden" name="signature" value="<?php echo $vars->signature ?>" />
</form>