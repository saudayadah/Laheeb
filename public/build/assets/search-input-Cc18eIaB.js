import{r,j as s}from"./app-BEcA6gqi.js";import{I as i}from"./input-DWjaG4QW.js";import{b as l,u as x}from"./createLucideIcon-pqjbkNGJ.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=[["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}],["path",{d:"m21 21-4.3-4.3",key:"1qie3q"}]],p=l("Search",f);function S({value:n,onChange:u,placeholder:a}){const{t:c}=x(),[e,m]=r.useState(n),o=r.useRef(!0);return r.useEffect(()=>{if(o.current){o.current=!1;return}const t=setTimeout(()=>u(e),350);return()=>clearTimeout(t)},[e]),s.jsxs("div",{className:"relative w-full max-w-xs",children:[s.jsx(p,{className:"text-muted-foreground absolute start-2.5 top-1/2 size-4 -translate-y-1/2"}),s.jsx(i,{value:e,onChange:t=>m(t.target.value),placeholder:a??c("common.search"),"aria-label":a??c("common.search"),className:"ps-8"})]})}export{S};
