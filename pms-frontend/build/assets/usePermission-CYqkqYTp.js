import{c as a,u as c,j as i}from"./index-BGKEJEuu.js";/**
 * @license lucide-vue-next v0.294.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const m=a("SaveIcon",[["path",{d:"M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z",key:"1owoqh"}],["polyline",{points:"17 21 17 13 7 13 7 21",key:"1md35c"}],["polyline",{points:"7 3 7 8 15 8",key:"8nz8an"}]]);function y(){const t=c();return{hasPermission:e=>t.canView(e),can:(e,n)=>t.can(e,n),canCreate:e=>t.canCreate(e),canUpdate:e=>t.canUpdate(e),canDelete:e=>t.canDelete(e),getAbilities:e=>i(()=>({view:t.canView(e),create:t.canCreate(e),update:t.canUpdate(e),delete:t.canDelete(e)})),canWrite:e=>t.canCreate(e)||t.canUpdate(e)||t.canDelete(e)}}export{m as S,y as u};
