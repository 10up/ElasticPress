!function(){"use strict";var e={5251:function(e,t,s){var r=s(9196),o=60103;if(t.Fragment=60107,"function"===typeof Symbol&&Symbol.for){var n=Symbol.for;o=n("react.element"),t.Fragment=n("react.fragment")}var c=r.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,a=Object.prototype.hasOwnProperty,i={key:!0,ref:!0,__self:!0,__source:!0};function l(e,t,s){var r,n={},l=null,p=null;for(r in void 0!==s&&(l=""+s),void 0!==t.key&&(l=""+t.key),void 0!==t.ref&&(p=t.ref),t)a.call(t,r)&&!i.hasOwnProperty(r)&&(n[r]=t[r]);if(e&&e.defaultProps)for(r in t=e.defaultProps)void 0===n[r]&&(n[r]=t[r]);return{$$typeof:o,type:e,key:l,ref:p,props:n,_owner:c.current}}t.jsx=l,t.jsxs=l},5893:function(e,t,s){e.exports=s(5251)},9196:function(e){e.exports=window.React}},t={};function s(r){var o=t[r];if(void 0!==o)return o.exports;var n=t[r]={exports:{}};return e[r](n,n.exports,s),n.exports}!function(){var e=window.wp.blocks,t=window.wp.blockEditor,r=window.wp.components,o=window.wp.element,n=window.wp.i18n,c=s(5893);const{searchablePostTypes:a}=window.epComments;var i=({attributes:e,setAttributes:s})=>{const{label:i,postTypes:l}=e,p=(0,t.useBlockProps)({className:"ep-widget-search-comments"}),m=(0,o.useMemo)((()=>0===l.length),[l]);return(0,c.jsxs)(c.Fragment,{children:[(0,c.jsxs)("div",{...p,children:[(0,c.jsx)(t.RichText,{"aria-label":(0,n.__)("Label text"),placeholder:(0,n.__)("Add label…"),withoutInteractiveFormatting:!0,value:i,onChange:e=>s({label:e})}),(0,c.jsx)("input",{autoComplete:"off",className:"ep-widget-search-comments-input",disabled:!0,type:"search"})]}),(0,c.jsx)(t.InspectorControls,{children:(0,c.jsxs)(r.PanelBody,{title:(0,n.__)("Search settings","elasticpress"),children:[(0,c.jsx)(r.CheckboxControl,{checked:m,label:(0,n.__)("Search all comments","elasticpress"),onChange:e=>{s(e?{postTypes:[]}:{postTypes:Object.keys(a)})}}),Object.entries(a).map((([e,t])=>{const o=(0,n.sprintf)((0,n.__)("Search comments on %s","elasticpress"),t);return(0,c.jsx)(r.CheckboxControl,{checked:l.includes(e),indeterminate:m,label:o,onChange:t=>((e,t)=>{const r=t?[...l,e]:l.filter((t=>t!==e));s({postTypes:r})})(e,t)})}))]})})]})},l=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"title":"Search Comments (ElasticPress)","icon":"search","textdomain":"elasticpress","name":"elasticpress/comments","category":"widgets","attributes":{"label":{"default":"Search comments","type":"string"},"postTypes":{"default":[],"type":"array"}},"editorScript":"elasticpress-comments-editor-script","script":"elasticpress-comments","style":"elasticpress-comments"}'),p={from:[{type:"block",blocks:["core/legacy-widget"],isMatch:({idBase:e})=>"ep-comments"===e,transform:({instance:t})=>{const{title:s=null,post_type:r}=t.raw;return s?[(0,e.createBlock)("core/heading",{content:s}),(0,e.createBlock)("elasticpress/comments",{postTypes:r})]:(0,e.createBlock)("elasticpress/comments",{postTypes:r})}}]};(0,e.registerBlockType)(l,{edit:e=>(0,c.jsx)(i,{...e}),save:()=>{},transforms:p})}()}();