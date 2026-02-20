var d=Object.defineProperty;var k=(o,e,t)=>e in o?d(o,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):o[e]=t;var c=(o,e,t)=>k(o,typeof e!="symbol"?e+"":e,t);import{c as l,ag as g,ah as f}from"./index-BGKEJEuu.js";import{M as p}from"./mail-B6o_U9Ix.js";/**
 * @license lucide-vue-next v0.294.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const w=l("FacebookIcon",[["path",{d:"M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z",key:"1jg4f8"}]]);/**
 * @license lucide-vue-next v0.294.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const I=l("GithubIcon",[["path",{d:"M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4",key:"tonef"}],["path",{d:"M9 18c-4.51 2-5-2-7-2",key:"9comsn"}]]);/**
 * @license lucide-vue-next v0.294.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=l("TwitterIcon",[["path",{d:"M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z",key:"pff0z6"}]]);let v=null;const y=()=>v,L=[{icon:w,color:"custom"},{icon:p,color:"orange"},{icon:S,color:"sky"},{icon:I,color:"slate"}];class u{constructor(e){c(this,"key");this.key=e}getItems(){const e=window.localStorage.getItem(this.key)||"{}";return JSON.parse(e)}setItems(e){window.localStorage.setItem(this.key,JSON.stringify(e))}removeItem(){window.localStorage.removeItem(this.key)}}const i=new u(g),m=new u(f);class E{constructor(){c(this,"users");const e=i.getItems();e.length?this.users=e:(this.users=[{username:"admin",password:"123456",phone:"123456",email:"admin@gmail.com"}],i.setItems(this.users))}getUser(){return m.getItems()}removeUser(){m.removeItem()}async login(e){const{username:t,password:a,phone:n,email:r}=e,h=this.users.find(s=>(s.username&&t&&s.username===t||s.phone&&n&&s.phone===n||s.email&&r&&s.email===r)&&s.password===a);if(h){const s={...h,token:"fake-token"};return m.setItems(s),s}else throw new Error("These credentials do not match our records.")}register(e){const{email:t,username:a}=e;if(this.users.find(r=>r.email===t||r.username===a))throw new Error("This record is already exists!");return this.users.push(e),i.setItems(this.users),"User created successfully!"}}const B=new E,_=y();export{_ as a,B as f,L as s};
