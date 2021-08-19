<div class="fel-invoice-container">
	
	<h1>FelInvoice Plugin</h1>
	<h2>Authentication key</h2>
	<div>
		<form method="post">
	    <?php $this->set_user_auth_keys(); ?>
        <?php /*$this->update_webhooks();*/ ?>
	    <p>
	        <span id="authkeyarea_help" class="help-block">You will receive the authentication key in your fel account.</span>
        </p>
            <?php submit_button('Save', "primary", "api_base_url_editor", false); ?>
            <?php submit_button('Check connection', "secondary", "check_connection", false); ?>
            <p><br></p>
        </form>
    </div>
    <h2>Custom Fields</h2>
    <div>
        <form method="post">
            <?php
                $this->set_order_trigger_field();
                submit_button('Save', "primary", "api_base_url_editor");
            ?>
        </form>
    </div>
</div>
<?php 

wp_enqueue_style('fel-invoice-css');

?>
