function slideInsta(wrap, delta){
	let slider_slides = wrap.querySelectorAll('.insta-slider-thumb'); 
	let slider_texts = wrap.querySelectorAll('.insta-slider-thumb-text'); 
	
	if(parseInt(wrap.dataset.loop) == 1){
		slider_slides[wrap.dataset.slide].classList.remove('active'); 
		slider_texts[wrap.dataset.slide].classList.remove('active'); 
		
		if((wrap.dataset.slide+delta)<0){
			wrap.dataset.slide = wrap.dataset.maxslide;
		}else if((wrap.dataset.slide+delta)>wrap.dataset.maxslide){
			wrap.dataset.slide = 0;
		}else{
			wrap.dataset.slide = parseInt(wrap.dataset.slide) + parseInt(delta); 
		}
		
		slider_slides[wrap.dataset.slide].classList.add('active');  
		slider_texts[wrap.dataset.slide].classList.add('active');  
	}else{
		if(((wrap.dataset.slide+delta)>0)&&((wrap.dataset.slide+delta)<wrap.dataset.maxslide)){ 
			slider_slides[wrap.dataset.slide].classList.remove('active'); 
			slider_texts[wrap.dataset.slide].classList.remove('active'); 
			wrap.dataset.slide = parseInt(wrap.dataset.slide) + parseInt(delta); 
			slider_slides[wrap.dataset.slide].classList.add('active');  
			slider_texts[wrap.dataset.slide].classList.add('active');  
		}
	}
}

document.addEventListener("DOMContentLoaded", ()=>{ 
	document.querySelectorAll(".insta-slider-wrap").forEach((el)=>{
		if(parseInt(el.dataset.autostart) == 1){
			setInterval(() => { if(parseInt(el.dataset.update) == 1) slideInsta(el, +1) }, parseInt(el.dataset.delay)*1000);
		}
		
		el.addEventListener("mouseover", (e)=>{ el.dataset.update = 0; });
		el.addEventListener("mouseout", (e)=>{ el.dataset.update = 1; });
	});
	
});
