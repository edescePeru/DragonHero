const initializeWorldMapEditor=editor=>{
  if(!editor||editor.dataset.worldMapEditorInitialized==='true')return;
  editor.dataset.worldMapEditorInitialized='true';

  const canvas=editor.querySelector('[data-map-canvas]');
  const image=editor.querySelector('[data-world-map-image]');
  const svg=editor.querySelector('[data-world-map-svg]');
  const statusNode=editor.querySelector('[data-world-map-status]');
  const createButton=editor.querySelector('[data-world-map-create]');
  const undoButton=editor.querySelector('[data-world-map-undo]');
  const clearButton=editor.querySelector('[data-world-map-clear]');
  const closeButton=editor.querySelector('[data-world-map-close]');
  const saveButton=editor.querySelector('[data-world-map-save]');
  const form=editor.querySelector('[data-area-form]');
  const required=[canvas,svg,statusNode,createButton,undoButton,clearButton,closeButton,saveButton,form];

  if(required.some(element=>!element)){
    [createButton,undoButton,clearButton,closeButton,saveButton].forEach(button=>{if(button)button.disabled=true;});
    editor.dataset.editorState='error';
    if(statusNode)statusNode.textContent='No se pudo inicializar el editor del mapa.';
    console.error('World Map Editor: faltan elementos obligatorios del DOM.');
    return;
  }

  const draftLayer=svg.querySelector('[data-draft-layer]');
  const input=form.querySelector('[data-polygon-input]');
  const readout=editor.querySelector('[data-coordinate-readout]');
  if(!draftLayer||!input||!readout){
    editor.dataset.editorState='error';
    statusNode.textContent='No se pudo inicializar el editor del mapa.';
    [createButton,undoButton,clearButton,closeButton,saveButton].forEach(button=>{button.disabled=true;});
    console.error('World Map Editor: faltan elementos de geometría obligatorios.');
    return;
  }

  const width=Number(editor.dataset.imageWidth);
  const height=Number(editor.dataset.imageHeight);
  form.dataset.createUrl=form.action;
  let state='loading',points=[],dirty=false,dragIndex=null,imageResolved=false;
  const messages={loading:'Cargando imagen del mapa...',ready:'Mapa listo. Pulsa Crear polígono para comenzar.',drawing:'Modo dibujo activo.',closed:'Polígono cerrado y listo para guardar.',error:'No se pudo cargar la imagen del mapa.'};

  const setEditorState=next=>{
    state=next;
    editor.dataset.editorState=state;
    statusNode.textContent=messages[state];
    statusNode.className=`alert py-2 ${state==='error'?'alert-danger':state==='closed'?'alert-success':'alert-info'}`;
    canvas.classList.toggle('is-drawing',state==='drawing');
    createButton.disabled=state!=='ready'&&state!=='closed';
    undoButton.disabled=state!=='drawing'||points.length===0;
    clearButton.disabled=(state!=='drawing'&&state!=='closed')||points.length===0;
    closeButton.disabled=state!=='drawing'||points.length<3;
    saveButton.disabled=state!=='closed';
  };
  const resolveReady=()=>{if(imageResolved)return;imageResolved=true;setEditorState('ready');render();};
  const resolveError=()=>{if(imageResolved)return;imageResolved=true;points=[];setEditorState('error');render();};
  const canonical=()=>({coordinate_system:'normalized',points:points.map(point=>({x:Number(point.x.toFixed(6)),y:Number(point.y.toFixed(6))}))});
  const normalizedPoint=event=>{
    if(width<=0||height<=0)return null;
    const matrix=svg.getScreenCTM();if(!matrix)return null;
    const point=svg.createSVGPoint();point.x=event.clientX;point.y=event.clientY;
    const local=point.matrixTransform(matrix.inverse());
    return{x:Math.max(0,Math.min(1,local.x/width)),y:Math.max(0,Math.min(1,local.y/height))};
  };
  const render=()=>{
    while(draftLayer.firstChild)draftLayer.removeChild(draftLayer.firstChild);
    if(points.length){const shape=document.createElementNS('http://www.w3.org/2000/svg',state==='closed'?'polygon':'polyline');shape.setAttribute('points',points.map(point=>`${point.x*width},${point.y*height}`).join(' '));shape.setAttribute('class',state==='closed'?'world-map-draft world-map-draft--closed':'world-map-draft');draftLayer.appendChild(shape);}
    if(state==='drawing')points.forEach((point,index)=>{const node=document.createElementNS('http://www.w3.org/2000/svg','circle');node.setAttribute('cx',point.x*width);node.setAttribute('cy',point.y*height);node.setAttribute('r',String(Math.max(5,width/180)));node.setAttribute('class','world-map-vertex');node.dataset.vertex=String(index);node.addEventListener('pointerdown',event=>{event.stopPropagation();dragIndex=index;node.setPointerCapture(event.pointerId);});draftLayer.appendChild(node);});
    input.value=points.length?JSON.stringify(canonical()):'';
    readout.textContent=points.length?`${points.length} puntos · ${state==='closed'?'polígono cerrado':'borrador sin guardar'}`:'Sin geometría preparada.';
    setEditorState(state);
  };
  const beginDrawing=()=>{if(points.length&&!window.confirm('Hay un borrador sin guardar. ¿Deseas reemplazarlo?'))return;points=[];dirty=true;setEditorState('drawing');render();};
  const clearDraft=()=>{if(!points.length||!window.confirm('¿Limpiar únicamente el borrador actual?'))return;points=[];dirty=true;setEditorState('ready');render();};

  svg.addEventListener('click',event=>{if(state!=='drawing'||event.target.closest('[data-area-id]')||event.target.dataset.vertex)return;const point=normalizedPoint(event);if(!point)return;points.push(point);dirty=true;render();});
  svg.addEventListener('pointermove',event=>{if(state!=='drawing'||dragIndex===null)return;const point=normalizedPoint(event);if(!point)return;points[dragIndex]=point;dirty=true;render();});
  svg.addEventListener('pointerup',()=>{dragIndex=null;});
  createButton.addEventListener('click',beginDrawing);
  undoButton.addEventListener('click',()=>{if(state!=='drawing'||!points.length)return;points.pop();dirty=true;render();});
  clearButton.addEventListener('click',clearDraft);
  closeButton.addEventListener('click',()=>{if(state!=='drawing'||points.length<3)return;dirty=true;setEditorState('closed');render();});
  const reset=()=>{form.reset();form.action=form.dataset.createUrl;form.querySelector('[data-method]').value='POST';form.querySelector('[data-area-version]').value='';points=[];dirty=false;setEditorState(imageResolved&&image&&image.naturalWidth>0&&image.naturalHeight>0?'ready':'error');render();};
  editor.querySelector('[data-new-area]').addEventListener('click',reset);
  document.querySelectorAll('[data-edit-area]').forEach(button=>button.addEventListener('click',()=>{let area;try{area=JSON.parse(button.dataset.area);}catch(error){setEditorState('error');console.error('World Map Editor: área inválida.',error);return;}form.action=button.dataset.updateUrl;form.querySelector('[data-method]').value='PUT';form.querySelector('[data-area-version]').value=area.version;Object.keys(area).forEach(key=>{const field=form.elements.namedItem(key);if(field&&area[key]!==null&&typeof area[key]!=='object')field.value=String(area[key]).replace(' ','T').slice(0,16);});points=area.polygon_points?area.polygon_points.points.slice():[];dirty=false;setEditorState(points.length>=3?'closed':'ready');render();form.scrollIntoView({behavior:'smooth'});}));
  form.addEventListener('input',()=>{dirty=true;});
  form.addEventListener('submit',event=>{if(state!=='closed'||!input.value){event.preventDefault();statusNode.textContent='Cierra un polígono válido antes de guardar el área.';statusNode.className='alert alert-danger py-2';return;}dirty=false;});
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});

  setEditorState('loading');
  if(!image||width<=0||height<=0){resolveError();return;}
  image.addEventListener('load',resolveReady,{once:true});
  image.addEventListener('error',resolveError,{once:true});
  if(image.complete){image.naturalWidth>0&&image.naturalHeight>0?resolveReady():resolveError();}
};

document.querySelectorAll('[data-world-map-editor]').forEach(initializeWorldMapEditor);
