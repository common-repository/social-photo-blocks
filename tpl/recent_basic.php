<div class="insta-grid-wrap" style="--cfg-align: <?php echo $cfg['align'];?>; --cfg-width: <?php echo $cfg['width'];?>; --cfg-rows: <?php echo $cfg['rows'];?>; --cfg-cols: <?php echo $cfg['cols'];?>; <?php if(!empty($cfg['height'])){ ?>padding-bottom: 0; height: <?php echo $cfg['height'];?>; <?php } ?>">
<div class="insta-grid-cnt">
<?php foreach($images as $image){ ?>
	<div class="insta-grid-thumb" style="background-image: url('<?php echo $image['media_url']; ?>');"></div>
<?php } ?>
</div>
</div>
