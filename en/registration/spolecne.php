<?php
require_once dirname(__FILE__).'/../../lib/init.php';

if (!$api)
	$api = new \HaveAPI\Client(API_URL);
?>

<label for="name">Additional info</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<?php $f->input('how') ?>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<?php $f->input('note'); ?>
	</div>

</div>

<label for="name">VPS settings</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<?php
			$opts = array();

			foreach ($api->os_template->list() as $tpl) {
				$opts[ $tpl->id ] = $tpl->label;
			}

			$f->select('distribution', $opts);
		?>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<?php
			$opts = array();
			$locations = $api->location->list(array(
				'environment' => ENVIRONMENT_ID,
				'has_hypervisor' => true,
			));

			foreach ($locations as $loc) {
				$opts[ $loc->id ] = 'Master Internet '.$loc->label;
			}

			$f->select('location', $opts);
		?>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<?php
			$f->select('currency', array(
				'czk' => '900 CZK/three months',
				'eur' => '36 EUR/three months',
			));
		?>
	</div>
<p>If you need an invoice, please send billing information to our <a href="/contact/">support team</a>.</p>
</div>

<?php
if ($f->isResubmit()) {
	$f->input('id', 'hidden');
	$f->input('token', 'hidden');
}
?>

<div class="row">
	<input class="btn btn-default" type="submit" id="send" value="Send">
</div>
