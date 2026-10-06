import{r,j as s}from"./app-CnkG-NlD.js";import{I as m}from"./input-B9YHu_Sg.js";import{d as l,u as x}from"./createLucideIcon-Dm9aHLsz.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=[["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}],["path",{d:"m21 21-4.3-4.3",key:"1qie3q"}]],p=l("Search",f);function S({value:c,onChange:o,placeholder:n}){const{t:u}=x(),[e,i]=r.useState(c),a=r.useRef(!0);return r.useEffect(()=>{if(a.current){a.current=!1;return}const t=setTimeout(()=>o(e),350);return()=>clearTimeout(t)},[e]),s.jsxs("div",{className:"relative w-full max-w-xs",children:[s.jsx(p,{className:"text-muted-foreground absolute start-2.5 top-1/2 size-4 -translate-y-1/2"}),s.jsx(m,{value:e,onChange:t=>i(t.target.value),placeholder:n??u("common.search"),className:"ps-8"})]})}export{S};
