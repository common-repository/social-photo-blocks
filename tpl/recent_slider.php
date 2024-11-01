<div class="insta-slider-wrap" style="--cfg-width: <?php echo $cfg['width'];?>; --cfg-height: <?php echo $cfg['height'];?>; --cfg-align: <?php echo $cfg['align'];?>;" data-slide="0" data-maxslide="<?php echo count($images)-1; ?>" data-autostart="<?php echo $cfg['autostart']; ?>" data-loop="<?php echo $cfg['loop']; ?>" data-delay="<?php echo $cfg['delay']; ?>" data-update="1">
	<div class="insta-slider-cnt">
	<?php $k = 0; foreach($images as $n=>$image){ $k++; ?>
		<div class="insta-slider-thumb<?php if($k==1){ echo ' active'; } ?>" style="background-image: url('<?php echo $image['media_url']; ?>'); z-index: <?php echo (count($images)*100-$n*100); ?>;">
		</div>
		<div class="insta-slider-thumb-text<?php if($k==1){ echo ' active'; } ?>" style="z-index: <?php echo (count($images)*100-$n*100)+10; ?>;"><?php echo $image['title']; ?></div>
	<?php } ?>
	</div>
	<div class="insta-slider-controls">
		<button class="display-left" onclick="slideInsta(this.parentNode.parentNode, -1);">&#10094;</button>
		<button class="display-right" onclick="slideInsta(this.parentNode.parentNode, 1);">&#10095;</button>
	</div>
</div>
