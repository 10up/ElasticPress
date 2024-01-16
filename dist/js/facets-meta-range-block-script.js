!function(){"use strict";var e={5251:function(e,t,s){var n=s(9196),i=60103;if(t.Fragment=60107,"function"===typeof Symbol&&Symbol.for){var o=Symbol.for;i=o("react.element"),t.Fragment=o("react.fragment")}var r=n.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,a=Object.prototype.hasOwnProperty,l={key:!0,ref:!0,__self:!0,__source:!0};function p(e,t,s){var n,o={},p=null,u=null;for(n in void 0!==s&&(p=""+s),void 0!==t.key&&(p=""+t.key),void 0!==t.ref&&(u=t.ref),t)a.call(t,n)&&!l.hasOwnProperty(n)&&(o[n]=t[n]);if(e&&e.defaultProps)for(n in t=e.defaultProps)void 0===o[n]&&(o[n]=t[n]);return{$$typeof:i,type:e,key:p,ref:u,props:o,_owner:r.current}}t.jsx=p,t.jsxs=p},5893:function(e,t,s){e.exports=s(5251)},9196:function(e){e.exports=window.React}},t={};function s(n){var i=t[n];if(void 0!==i)return i.exports;var o=t[n]={exports:{}};return e[n](o,o.exports,s),o.exports}!function(){var e=window.wp.blocks,t=window.wp.primitives,n=s(5893),i=()=>(0,n.jsxs)(t.SVG,{version:"1.0",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 24 24",children:[(0,n.jsx)(t.Path,{d:"M 9 11 L 15 11 L 15 13 L 9 13 Z"}),(0,n.jsx)(t.Path,{d:"M18.5 11H17.5V13H18.5V11ZM20 13C20.5523 13 21 12.5523 21 12C21 11.4477 20.5523 11 20 11V13ZM16.5 9C16.5 8.44772 16.0523 8 15.5 8C14.9477 8 14.5 8.44772 14.5 9H16.5ZM8 9C8 8.44772 7.55228 8 7 8C6.44772 8 6 8.44772 6 9H8ZM6 15C6 15.5523 6.44772 16 7 16C7.55228 16 8 15.5523 8 15H6ZM14.5 15C14.5 15.5523 14.9477 16 15.5 16C16.0523 16 16.5 15.5523 16.5 15H14.5ZM18.5 13H20V11H18.5V13ZM6 9V15H8V9H6ZM14.5 9V15H16.5V9H14.5Z"}),(0,n.jsx)(t.Path,{d:"M4 11C3.44772 11 3 11.4477 3 12C3 12.5523 3.44772 13 4 13V11ZM4 13H7V11H4V13Z"})]}),o=JSON.parse('{"u2":"elasticpress/facet-meta-range"}'),r=window.wp.blockEditor,a=window.wp.components,l=window.wp.data,p=window.wp.i18n,u=()=>(0,n.jsx)(a.Placeholder,{children:(0,n.jsx)(a.Spinner,{})}),c=window.wp.element,h=({onChange:e,value:t})=>{const s=(0,l.useSelect)((e=>e("elasticpress").getMetaKeys())),i=(0,c.useMemo)((()=>[{label:(0,p.__)("Select key","elasticpress"),value:""},...s.map((e=>({label:e,value:e})))]),[s]);return(0,n.jsx)(a.SelectControl,{disabled:i.length<=1,help:(0,c.createInterpolateElement)((0,p.__)("This is the list of metadata fields indexed in Elasticsearch. If your desired field does not appear in this list please try to <a>sync your content</a>","elasticpress"),{a:(0,n.jsx)("a",{href:window.epBlocks.syncUrl})}),label:(0,p.__)("Filter by","elasticpress"),onChange:e,options:i,value:t})};function d(e,t){return d=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(e,t){return e.__proto__=t,e},d(e,t)}var f=s(9196);function m(e){return e&&e.stopPropagation&&e.stopPropagation(),e&&e.preventDefault&&e.preventDefault(),!1}function v(e){return null==e?[]:Array.isArray(e)?e.slice():[e]}function g(e){return null!==e&&1===e.length?e[0]:e.slice()}function b(e){Object.keys(e).forEach((t=>{"undefined"!=typeof document&&document.addEventListener(t,e[t],!1)}))}function y(e,t){return x(function(e,t){let s=e;return s<=t.min&&(s=t.min),s>=t.max&&(s=t.max),s}(e,t),t)}function x(e,t){const s=(e-t.min)%t.step;let n=e-s;return 2*Math.abs(s)>=t.step&&(n+=s>0?t.step:-t.step),parseFloat(n.toFixed(5))}let w=function(e){function t(t){var s;(s=e.call(this,t)||this).onKeyUp=()=>{s.onEnd()},s.onMouseUp=()=>{s.onEnd(s.getMouseEventMap())},s.onTouchEnd=e=>{e.preventDefault(),s.onEnd(s.getTouchEventMap())},s.onBlur=()=>{s.setState({index:-1},s.onEnd(s.getKeyDownEventMap()))},s.onMouseMove=e=>{s.setState({pending:!0});const t=s.getMousePosition(e),n=s.getDiffPosition(t[0]),i=s.getValueFromPosition(n);s.move(i)},s.onTouchMove=e=>{if(e.touches.length>1)return;s.setState({pending:!0});const t=s.getTouchPosition(e);if(void 0===s.isScrolling){const e=t[0]-s.startPosition[0],n=t[1]-s.startPosition[1];s.isScrolling=Math.abs(n)>Math.abs(e)}if(s.isScrolling)return void s.setState({index:-1});const n=s.getDiffPosition(t[0]),i=s.getValueFromPosition(n);s.move(i)},s.onKeyDown=e=>{if(!(e.ctrlKey||e.shiftKey||e.altKey||e.metaKey))switch(s.setState({pending:!0}),e.key){case"ArrowLeft":case"ArrowDown":case"Left":case"Down":e.preventDefault(),s.moveDownByStep();break;case"ArrowRight":case"ArrowUp":case"Right":case"Up":e.preventDefault(),s.moveUpByStep();break;case"Home":e.preventDefault(),s.move(s.props.min);break;case"End":e.preventDefault(),s.move(s.props.max);break;case"PageDown":e.preventDefault(),s.moveDownByStep(s.props.pageFn(s.props.step));break;case"PageUp":e.preventDefault(),s.moveUpByStep(s.props.pageFn(s.props.step))}},s.onSliderMouseDown=e=>{if(!s.props.disabled&&2!==e.button){if(s.setState({pending:!0}),!s.props.snapDragDisabled){const t=s.getMousePosition(e);s.forceValueFromPosition(t[0],(e=>{s.start(e,t[0]),b(s.getMouseEventMap())}))}m(e)}},s.onSliderClick=e=>{if(!s.props.disabled&&s.props.onSliderClick&&!s.hasMoved){const t=s.getMousePosition(e),n=y(s.calcValue(s.calcOffsetFromPosition(t[0])),s.props);s.props.onSliderClick(n)}},s.createOnKeyDown=e=>t=>{s.props.disabled||(s.start(e),b(s.getKeyDownEventMap()),m(t))},s.createOnMouseDown=e=>t=>{if(s.props.disabled||2===t.button)return;s.setState({pending:!0});const n=s.getMousePosition(t);s.start(e,n[0]),b(s.getMouseEventMap()),m(t)},s.createOnTouchStart=e=>t=>{if(s.props.disabled||t.touches.length>1)return;s.setState({pending:!0});const n=s.getTouchPosition(t);s.startPosition=n,s.isScrolling=void 0,s.start(e,n[0]),b(s.getTouchEventMap()),function(e){e.stopPropagation&&e.stopPropagation()}(t)},s.handleResize=()=>{const e=window.setTimeout((()=>{s.pendingResizeTimeouts.shift(),s.resize()}),0);s.pendingResizeTimeouts.push(e)},s.renderThumb=(e,t)=>{const n=s.props.thumbClassName+" "+s.props.thumbClassName+"-"+t+" "+(s.state.index===t?s.props.thumbActiveClassName:""),i={ref:e=>{s["thumb"+t]=e},key:s.props.thumbClassName+"-"+t,className:n,style:e,onMouseDown:s.createOnMouseDown(t),onTouchStart:s.createOnTouchStart(t),onFocus:s.createOnKeyDown(t),tabIndex:0,role:"slider","aria-orientation":s.props.orientation,"aria-valuenow":s.state.value[t],"aria-valuemin":s.props.min,"aria-valuemax":s.props.max,"aria-label":Array.isArray(s.props.ariaLabel)?s.props.ariaLabel[t]:s.props.ariaLabel,"aria-labelledby":Array.isArray(s.props.ariaLabelledby)?s.props.ariaLabelledby[t]:s.props.ariaLabelledby,"aria-disabled":s.props.disabled},o={index:t,value:g(s.state.value),valueNow:s.state.value[t]};return s.props.ariaValuetext&&(i["aria-valuetext"]="string"==typeof s.props.ariaValuetext?s.props.ariaValuetext:s.props.ariaValuetext(o)),s.props.renderThumb(i,o)},s.renderTrack=(e,t,n)=>{const i={key:s.props.trackClassName+"-"+e,className:s.props.trackClassName+" "+s.props.trackClassName+"-"+e,style:s.buildTrackStyle(t,s.state.upperBound-n)},o={index:e,value:g(s.state.value)};return s.props.renderTrack(i,o)};let n=v(t.value);n.length||(n=v(t.defaultValue)),s.pendingResizeTimeouts=[];const i=[];for(let e=0;e<n.length;e+=1)n[e]=y(n[e],t),i.push(e);return s.resizeObserver=null,s.resizeElementRef=f.createRef(),s.state={index:-1,upperBound:0,sliderLength:0,value:n,zIndices:i},s}var s,n;n=e,(s=t).prototype=Object.create(n.prototype),s.prototype.constructor=s,d(s,n);var i=t.prototype;return i.componentDidMount=function(){"undefined"!=typeof window&&(this.resizeObserver=new ResizeObserver(this.handleResize),this.resizeObserver.observe(this.resizeElementRef.current),this.resize())},t.getDerivedStateFromProps=function(e,t){const s=v(e.value);return s.length?t.pending?null:{value:s.map((t=>y(t,e)))}:null},i.componentDidUpdate=function(){0===this.state.upperBound&&this.resize()},i.componentWillUnmount=function(){this.clearPendingResizeTimeouts(),this.resizeObserver&&this.resizeObserver.disconnect()},i.onEnd=function(e){e&&function(e){Object.keys(e).forEach((t=>{"undefined"!=typeof document&&document.removeEventListener(t,e[t],!1)}))}(e),this.hasMoved&&this.fireChangeEvent("onAfterChange"),this.setState({pending:!1}),this.hasMoved=!1},i.getValue=function(){return g(this.state.value)},i.getClosestIndex=function(e){let t=Number.MAX_VALUE,s=-1;const{value:n}=this.state,i=n.length;for(let o=0;o<i;o+=1){const i=this.calcOffset(n[o]),r=Math.abs(e-i);r<t&&(t=r,s=o)}return s},i.getMousePosition=function(e){return[e["page"+this.axisKey()],e["page"+this.orthogonalAxisKey()]]},i.getTouchPosition=function(e){const t=e.touches[0];return[t["page"+this.axisKey()],t["page"+this.orthogonalAxisKey()]]},i.getKeyDownEventMap=function(){return{keydown:this.onKeyDown,keyup:this.onKeyUp,focusout:this.onBlur}},i.getMouseEventMap=function(){return{mousemove:this.onMouseMove,mouseup:this.onMouseUp}},i.getTouchEventMap=function(){return{touchmove:this.onTouchMove,touchend:this.onTouchEnd}},i.getValueFromPosition=function(e){const t=e/(this.state.sliderLength-this.state.thumbSize)*(this.props.max-this.props.min);return y(this.state.startValue+t,this.props)},i.getDiffPosition=function(e){let t=e-this.state.startPosition;return this.props.invert&&(t*=-1),t},i.resize=function(){const{slider:e,thumb0:t}=this;if(!e||!t)return;const s=this.sizeKey(),n=e.getBoundingClientRect(),i=e[s],o=n[this.posMaxKey()],r=n[this.posMinKey()],a=t.getBoundingClientRect()[s.replace("client","").toLowerCase()],l=i-a,p=Math.abs(o-r);this.state.upperBound===l&&this.state.sliderLength===p&&this.state.thumbSize===a||this.setState({upperBound:l,sliderLength:p,thumbSize:a})},i.calcOffset=function(e){const t=this.props.max-this.props.min;return 0===t?0:(e-this.props.min)/t*this.state.upperBound},i.calcValue=function(e){return e/this.state.upperBound*(this.props.max-this.props.min)+this.props.min},i.calcOffsetFromPosition=function(e){const{slider:t}=this,s=t.getBoundingClientRect(),n=s[this.posMaxKey()],i=s[this.posMinKey()];let o=e-(window["page"+this.axisKey()+"Offset"]+(this.props.invert?n:i));return this.props.invert&&(o=this.state.sliderLength-o),o-=this.state.thumbSize/2,o},i.forceValueFromPosition=function(e,t){const s=this.calcOffsetFromPosition(e),n=this.getClosestIndex(s),i=y(this.calcValue(s),this.props),o=this.state.value.slice();o[n]=i;for(let e=0;e<o.length-1;e+=1)if(o[e+1]-o[e]<this.props.minDistance)return;this.fireChangeEvent("onBeforeChange"),this.hasMoved=!0,this.setState({value:o},(()=>{t(n),this.fireChangeEvent("onChange")}))},i.clearPendingResizeTimeouts=function(){do{const e=this.pendingResizeTimeouts.shift();clearTimeout(e)}while(this.pendingResizeTimeouts.length)},i.start=function(e,t){const s=this["thumb"+e];s&&s.focus();const{zIndices:n}=this.state;n.splice(n.indexOf(e),1),n.push(e),this.setState((s=>({startValue:s.value[e],startPosition:void 0!==t?t:s.startPosition,index:e,zIndices:n})))},i.moveUpByStep=function(e){void 0===e&&(e=this.props.step);const t=this.state.value[this.state.index],s=y(this.props.invert&&"horizontal"===this.props.orientation?t-e:t+e,this.props);this.move(Math.min(s,this.props.max))},i.moveDownByStep=function(e){void 0===e&&(e=this.props.step);const t=this.state.value[this.state.index],s=y(this.props.invert&&"horizontal"===this.props.orientation?t+e:t-e,this.props);this.move(Math.max(s,this.props.min))},i.move=function(e){const t=this.state.value.slice(),{index:s}=this.state,{length:n}=t,i=t[s];if(e===i)return;this.hasMoved||this.fireChangeEvent("onBeforeChange"),this.hasMoved=!0;const{pearling:o,max:r,min:a,minDistance:l}=this.props;if(!o){if(s>0){const n=t[s-1];e<n+l&&(e=n+l)}if(s<n-1){const n=t[s+1];e>n-l&&(e=n-l)}}t[s]=e,o&&n>1&&(e>i?(this.pushSucceeding(t,l,s),function(e,t,s,n){for(let i=0;i<e;i+=1){const o=n-i*s;t[e-1-i]>o&&(t[e-1-i]=o)}}(n,t,l,r)):e<i&&(this.pushPreceding(t,l,s),function(e,t,s,n){for(let i=0;i<e;i+=1){const e=n+i*s;t[i]<e&&(t[i]=e)}}(n,t,l,a))),this.setState({value:t},this.fireChangeEvent.bind(this,"onChange"))},i.pushSucceeding=function(e,t,s){let n,i;for(n=s,i=e[n]+t;null!==e[n+1]&&i>e[n+1];n+=1,i=e[n]+t)e[n+1]=x(i,this.props)},i.pushPreceding=function(e,t,s){for(let n=s,i=e[n]-t;null!==e[n-1]&&i<e[n-1];n-=1,i=e[n]-t)e[n-1]=x(i,this.props)},i.axisKey=function(){return"vertical"===this.props.orientation?"Y":"X"},i.orthogonalAxisKey=function(){return"vertical"===this.props.orientation?"X":"Y"},i.posMinKey=function(){return"vertical"===this.props.orientation?this.props.invert?"bottom":"top":this.props.invert?"right":"left"},i.posMaxKey=function(){return"vertical"===this.props.orientation?this.props.invert?"top":"bottom":this.props.invert?"left":"right"},i.sizeKey=function(){return"vertical"===this.props.orientation?"clientHeight":"clientWidth"},i.fireChangeEvent=function(e){this.props[e]&&this.props[e](g(this.state.value),this.state.index)},i.buildThumbStyle=function(e,t){const s={position:"absolute",touchAction:"none",willChange:this.state.index>=0?this.posMinKey():void 0,zIndex:this.state.zIndices.indexOf(t)+1};return s[this.posMinKey()]=e+"px",s},i.buildTrackStyle=function(e,t){const s={position:"absolute",willChange:this.state.index>=0?this.posMinKey()+","+this.posMaxKey():void 0};return s[this.posMinKey()]=e,s[this.posMaxKey()]=t,s},i.buildMarkStyle=function(e){var t;return(t={position:"absolute"})[this.posMinKey()]=e,t},i.renderThumbs=function(e){const{length:t}=e,s=[];for(let n=0;n<t;n+=1)s[n]=this.buildThumbStyle(e[n],n);const n=[];for(let e=0;e<t;e+=1)n[e]=this.renderThumb(s[e],e);return n},i.renderTracks=function(e){const t=[],s=e.length-1;t.push(this.renderTrack(0,0,e[0]));for(let n=0;n<s;n+=1)t.push(this.renderTrack(n+1,e[n],e[n+1]));return t.push(this.renderTrack(s+1,e[s],this.state.upperBound)),t},i.renderMarks=function(){let{marks:e}=this.props;const t=this.props.max-this.props.min+1;return"boolean"==typeof e?e=Array.from({length:t}).map(((e,t)=>t)):"number"==typeof e&&(e=Array.from({length:t}).map(((e,t)=>t)).filter((t=>t%e==0))),e.map(parseFloat).sort(((e,t)=>e-t)).map((e=>{const t=this.calcOffset(e),s={key:e,className:this.props.markClassName,style:this.buildMarkStyle(t)};return this.props.renderMark(s)}))},i.render=function(){const e=[],{value:t}=this.state,s=t.length;for(let n=0;n<s;n+=1)e[n]=this.calcOffset(t[n],n);const n=this.props.withTracks?this.renderTracks(e):null,i=this.renderThumbs(e),o=this.props.marks?this.renderMarks():null;return f.createElement("div",{ref:e=>{this.slider=e,this.resizeElementRef.current=e},style:{position:"relative"},className:this.props.className+(this.props.disabled?" disabled":""),onMouseDown:this.onSliderMouseDown,onClick:this.onSliderClick},n,i,o)},t}(f.Component);w.displayName="ReactSlider",w.defaultProps={min:0,max:100,step:1,pageFn:e=>10*e,minDistance:0,defaultValue:0,orientation:"horizontal",className:"slider",thumbClassName:"thumb",thumbActiveClassName:"active",trackClassName:"track",markClassName:"mark",withTracks:!0,pearling:!1,disabled:!1,snapDragDisabled:!1,invert:!1,marks:[],renderThumb:e=>f.createElement("div",e),renderTrack:e=>f.createElement("div",e),renderMark:e=>f.createElement("span",e)};var M=w,C=({clearUrl:e,min:t,max:s,prefix:i,suffix:o,value:r,...a})=>(window.Cypress&&(window.app={sliderChange:a.onChange}),(0,n.jsxs)("div",{className:"ep-range-facet",children:[(0,n.jsx)("div",{className:"ep-range-facet__slider",children:(0,n.jsx)(M,{className:"ep-range-slider",minDistance:1,thumbActiveClassName:"ep-range-slider__thumb--active",thumbClassName:"ep-range-slider__thumb",trackClassName:"ep-range-slider__track",min:t,max:s,value:r,...a})}),(0,n.jsxs)("div",{className:"ep-range-facet__values",children:[i,r[0],o," — ",i,r[1],o]}),(0,n.jsxs)("div",{className:"ep-range-facet__action",children:[e?(0,n.jsx)("a",{href:e,children:(0,p.__)("Clear","elasticpress")}):null," ",(0,n.jsx)("button",{className:"wp-element-button",type:"submit",children:(0,p.__)("Filter","elasticpress")})]})]})),k={from:[{type:"block",blocks:["elasticpress/facet-meta"],transform:t=>(0,e.createBlock)("elasticpress/facet-meta-range",t)}]};(0,e.registerBlockType)(o.u2,{icon:i,edit:({attributes:t,name:s,setAttributes:o})=>{const{facet:c,prefix:d,suffix:f}=t,{title:m}=(0,e.getBlockType)(s),v=(0,r.useBlockProps)(),{min:g=!1,max:b=!1,isLoading:y=!1}=(0,l.useSelect)((e=>e("elasticpress").getMetaRange(c)||{isLoading:!0}),[c]),x=e=>{o({facet:e})};return(0,n.jsxs)(n.Fragment,{children:[(0,n.jsx)(r.InspectorControls,{children:(0,n.jsxs)(a.PanelBody,{title:(0,p.__)("Settings","elasticpress"),children:[(0,n.jsx)(h,{onChange:x,value:c}),(0,n.jsxs)(a.Flex,{children:[(0,n.jsx)(a.FlexItem,{children:(0,n.jsx)(a.TextControl,{label:(0,p.__)("Value prefix","elasticpress"),onChange:e=>{o({prefix:e})},value:d})}),(0,n.jsx)(a.FlexItem,{children:(0,n.jsx)(a.TextControl,{label:(0,p.__)("Value suffix","elasticpress"),onChange:e=>{o({suffix:e})},value:f})})]})]})}),(0,n.jsx)("div",{...v,children:c?y?(0,n.jsx)(u,{}):!1!==g&&!1!==b?(0,n.jsx)(a.Disabled,{children:(0,n.jsx)(C,{min:g,max:b,prefix:d,suffix:f,value:[g,b]})}):(0,n.jsx)(r.Warning,{children:(0,p.sprintf)((0,p.__)('Preview unavailable. The "%s" field does not appear to contain numeric values. Select a new meta field key or populate the field with numeric values to enable filtering by range.',"elasticpress"),c)}):(0,n.jsx)(a.Placeholder,{icon:i,label:m,children:(0,n.jsx)(h,{onChange:x,value:c})})})]})},save:()=>{},transforms:k})}()}();