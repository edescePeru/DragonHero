const mapForm=document.querySelector('[data-world-map-form]');
if(mapForm){
  const world=mapForm.querySelector('[data-world-select]');
  const region=mapForm.querySelector('[data-region-select]');
  world.addEventListener('change',()=>{if(world.value)region.value='';});
  region.addEventListener('change',()=>{if(region.value)world.value='';});
}
