"use strict";
const KEY="cleandrive-gso-prototype-v1";
const routes=[
 {id:"R-101",name:"Jaro North Collection Route",stops:[{barangay:"Balabago",name:"Balabago Barangay Hall",arrival:"07:30",status:"Scheduled"},{barangay:"Tagbak",name:"Tagbak Public Market",arrival:"08:15",status:"Scheduled"},{barangay:"Tacas",name:"Tacas Community Center",arrival:"09:00",status:"Scheduled"}]},
 {id:"R-102",name:"Mandurriao Central Route",stops:[{barangay:"Dungon C",name:"Dungon C Covered Court",arrival:"08:00",status:"Scheduled"},{barangay:"Airport",name:"Airport Road Collection Point",arrival:"09:10",status:"Scheduled"}]},
 {id:"R-103",name:"La Paz East Collection Route",stops:[{barangay:"Burgos-Mabini-Plaza",name:"La Paz Plaza",arrival:"07:45",status:"Scheduled"},{barangay:"Lopez Jaena Norte",name:"Lopez Jaena Station",arrival:"09:30",status:"Scheduled"}]}
];
const initial={
 barangays:[
 {id:1,name:"Balabago",city:"Iloilo City",contact:"Maria Santos",phone:"09171234567",email:"balabago@example.test",status:"Active",document:""},
 {id:2,name:"Tagbak",city:"Iloilo City",contact:"Juan Dela Cruz",phone:"09181234567",email:"tagbak@example.test",status:"Active",document:""},
 {id:3,name:"Dungon C",city:"Iloilo City",contact:"Ana Reyes",phone:"09191234567",email:"dungonc@example.test",status:"Pending",document:""}],
 contractors:[
 {id:1,name:"Greenway Collection Services",license:"LIC-DEMO-101",phone:"09170000001",email:"greenway@example.test",address:"Iloilo City",status:"Active",collectors:4,vehicles:2,routes:2,runs:12},
 {id:2,name:"EcoHaul Logistics",license:"LIC-DEMO-102",phone:"09170000002",email:"ecohaul@example.test",address:"Iloilo City",status:"Active",collectors:3,vehicles:2,routes:1,runs:8}],
 schedules:[
 {id:1,route:"R-101",from:"2026-11-01",until:"2026-12-31",days:["Mon","Wed","Fri"],status:"Active"},
 {id:2,route:"R-102",from:"2026-11-01",until:"2026-12-31",days:["Tue","Thu","Sat"],status:"Active"},
 {id:3,route:"R-103",from:"2026-11-05",until:"2026-12-31",days:["Mon","Thu"],status:"Inactive"}],
 operations:[
 {id:1,date:"2026-11-18",route:"R-101",contractor:"Greenway Collection Services",vehicle:"TRK-001",status:"Completed",completed:3,stops:3},
 {id:2,date:"2026-11-18",route:"R-102",contractor:"EcoHaul Logistics",vehicle:"TRK-002",status:"Traveling",completed:0,stops:2},
 {id:3,date:"2026-11-18",route:"R-103",contractor:"Greenway Collection Services",vehicle:"TRK-003",status:"Delayed",completed:1,stops:2}],
 complaints:[
 {date:"2026-11-17",type:"Missed pickup",related:"Balabago",status:"Resolved"},
 {date:"2026-11-18",type:"Delayed collection",related:"Dungon C",status:"Pending"}]
};
const clone=x=>JSON.parse(JSON.stringify(x));
let store;try{store=JSON.parse(localStorage.getItem(KEY))||clone(initial)}catch{store=clone(initial)}
for(const k of Object.keys(initial))if(!Array.isArray(store[k]))store[k]=clone(initial[k]);
let page="overview",editingBarangay=null,editingContractor=null,editingSchedule=null,selectedRoute="R-101",category="Collection Performance",reportRows=[],reportGenerated=false;
let gsoTracking=[];
let gsoTrackingTimer=null;
let googleMap=null;
let googleMapMarkers=new Map();
let googleMapsLoading=null;
const titles={overview:"Dashboard Overview",barangays:"Manage Barangays",contractors:"Manage Contractors",schedules:"Manage Schedules",reports:"Manage Reports"};
const $=s=>document.querySelector(s), esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const save=()=>{try{localStorage.setItem(KEY,JSON.stringify(store))}catch{toast("Browser storage full. Data may not be saved.")}};
const rName=id=>routes.find(r=>r.id===id)?.name||"Unknown route";
const badge=s=>`<span class="pill ${esc(String(s).toLowerCase().replace(/\s/g,"-"))}">${esc(s)}</span>`;
const stat=(label,n,icon)=>`<article class="stat"><div><small>${esc(label)}</small><b>${esc(n)}</b></div><span class="stat-icon">${icon}</span></article>`;
const panel=(title,content,sub="")=>`<section class="panel"><div class="panel-head"><div><h2>${title}</h2>${sub?`<p class="sub">${sub}</p>`:""}</div></div>${content}</section>`;
const field=(title,name,type="text",value="",required=true,extra="")=>`<div class="field"><label for="${name}">${title}</label><input id="${name}" name="${name}" type="${type}" value="${esc(value)}" ${required?"required":""} ${extra}></div>`;
const select=(title,name,options,selected="",required=true)=>`<div class="field"><label for="${name}">${title}</label><select id="${name}" name="${name}" ${required?"required":""}><option value="">Select ${title.toLowerCase()}</option>${options.map(v=>{const [value,label]=Array.isArray(v)?v:[v,v];return `<option value="${esc(value)}" ${String(value)===String(selected)?"selected":""}>${esc(label)}</option>`}).join("")}</select></div>`;
const table=(heads,rows,empty="No records found.")=>`<div class="table-scroll"><table class="data-table"><thead><tr>${heads.map(h=>`<th>${esc(h)}</th>`).join("")}</tr></thead><tbody>${rows.length?rows.join(""):`<tr><td class="empty" colspan="${heads.length}">${esc(empty)}</td></tr>`}</tbody></table></div>`;
const heading=(title,subtitle,actions="")=>`<div class="heading"><div><div class="eyebrow">General Services Office / GSO Portal</div><h1>${title}</h1><p>${subtitle}</p></div>${actions}</div>`;
const optsStatus=(v)=>select("Registration Status","status",["Active","Pending","Inactive"],v);
const toast=m=>{const el=$("#toast");el.textContent=m;el.classList.add("visible");clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>el.classList.remove("visible"),3500)};
function confirmBox(title,details,yes){$("#modal").innerHTML=`<div class="backdrop"><div class="dialog" role="dialog" aria-modal="true"><h2>${esc(title)}</h2><p>${esc(details)}</p><div class="actions"><button class="btn" id="cancel-dialog">Cancel</button><button class="btn primary" id="confirm-dialog">Confirm</button></div></div></div>`;$("#cancel-dialog").onclick=()=>$("#modal").innerHTML="";$("#confirm-dialog").onclick=()=>{$("#modal").innerHTML="";yes()};}
function setPage(p){if(!titles[p])return;page=p;history.replaceState(null,"","#"+p);render();window.scrollTo(0,0);$("#sidebar").classList.remove("open")}
function renderNav(){const icons={overview:"▦",barangays:"▤",contractors:"♙",schedules:"▦",reports:"▥"};$("#nav").innerHTML=Object.entries(titles).map(([id,title])=>`<button class="nav-button ${page===id?"active":""}" data-page="${id}">${icons[id]} &nbsp; ${title}</button>`).join("")}
function render(){renderNav();$("#main").innerHTML=({overview:overview,barangays:barangays,contractors:contractors,schedules:schedules,reports:reports})[page]();bindPage();if(page==="overview")startGsoTracking();else stopGsoTracking()}

function startGsoTracking(){
 if(gsoTrackingTimer)return;
 loadGsoTracking();
 gsoTrackingTimer=setInterval(loadGsoTracking,10000);
}

function stopGsoTracking(){
 if(gsoTrackingTimer){clearInterval(gsoTrackingTimer);gsoTrackingTimer=null}
}

function overview(){
 const statuses=["Scheduled","Traveling","In Progress","Delayed","Missed","Completed"];
 const counts=statuses.map(s=>({s,n:store.operations.filter(x=>x.status===s).length}));
 const ops=store.operations.map(o=>`<tr><td>${esc(o.date)}</td><td>${esc(rName(o.route))}</td><td>${esc(o.contractor)}</td><td>${esc(o.vehicle)}</td><td>${badge(o.status)}</td><td>${esc(o.completed)}/${esc(o.stops)}</td></tr>`);
 const map=`<div class="map live-tracking-map" id="gso-live-map"><p class="muted">Loading live collector locations…</p></div><div class="map-caption">Live positions come from Collector GPS updates stored in vehicle_location.</div>`;
 return heading("Dashboard Overview","Collection monitoring and operational summary")+
 `<div class="stats">${stat("Registered Barangays",store.barangays.length,"▤")}${stat("Registered Contractors",store.contractors.length,"♙")}${stat("Active Routes",routes.length,"⌁")}${stat("Scheduled Collections",store.schedules.filter(x=>x.status==="Active").length,"▦")}</div>`+
 `<div class="grid-monitor">${panel("Waste Collection Monitoring Map",map,"Illustrative collection coverage")}${panel("Collection Status Summary",`<div class="statuses">${counts.map(c=>`<div class="status-row"><span><i class="dot ${esc(c.s.toLowerCase().replace(/\s/g,"-"))}"></i>${c.s}</span><b>${c.n}</b></div>`).join("")}</div>`,"Sample collection operation statuses")}</div>`+
 panel("Today's Collection Operations",table(["Date","Route","Contractor","Vehicle","Run Status","Completed Stops"],ops),"Sample operations for demonstration; not live tracking");
}
function barangays(){
 const rows=store.barangays.map(b=>`<tr data-search="${esc((b.name+" "+b.city+" "+b.contact).toLowerCase())}"><td><b>${esc(b.name)}</b></td><td>${esc(b.city)}</td><td>${esc(b.contact)}</td><td>${esc(b.phone)}</td><td>${badge(b.status)}</td><td><button class="btn small" data-b-action="view" data-id="${b.id}">View</button><button class="btn small" data-b-action="edit" data-id="${b.id}">Edit</button><button class="btn small ${b.status==="Inactive"?"":"danger"}" data-b-action="toggle" data-id="${b.id}">${b.status==="Inactive"?"Enable":"Disable"}</button></td></tr>`);
 const b=store.barangays.find(x=>x.id===editingBarangay)||{};
 return heading("Manage Barangays","Register, verify, and manage participating barangays")+
 `<div class="stats three">${stat("Registered Barangays",store.barangays.length,"▤")}${stat("Active Registrations",store.barangays.filter(x=>x.status==="Active").length,"✓")}${stat("Pending / Inactive",store.barangays.filter(x=>x.status!=="Active").length,"◷")}</div>`+
 `<section class="panel"><div class="panel-head"><div><h2>Barangay Registration Records</h2><p class="sub">Manage registered barangays and supporting documentation</p></div><div class="toolbar"><input id="search-b" aria-label="Search barangays" placeholder="Search barangay or contact"><button class="btn primary" id="new-b">+ Register Barangay</button><button class="btn" id="docs-b">View Documents</button></div></div>${table(["Barangay","City","GSO Contact","Contact Number","Status","Actions"],rows)}</section>`+
 `<section class="panel space" id="barangay-form-panel"><div class="panel-head"><h2>${editingBarangay?"Edit Barangay Registration":"Registration / Edit Form"}</h2>${editingBarangay?`<span class="pill active">Editing: ${esc(b.name)}</span>`:""}</div><form id="form-b"><div class="field-grid three">${field("Barangay","name","text",b.name)}${field("City","city","text",b.city||"Iloilo City")}${field("Contact Person","contact","text",b.contact)}${field("Contact Number","phone","tel",b.phone)}${optsStatus(b.status||"Active")}${field("Email Address","email","email",b.email||"")}</div><div class="field space"><label for="document">Supporting Documentation (demo filename only)</label><input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png">${b.document?`<small class="file-hint">Current: ${esc(b.document)}</small>`:""}<small class="file-hint">Files are not uploaded or stored in this browser prototype.</small></div><div class="form-actions"><button type="button" class="btn" id="clear-b">Clear</button><button type="submit" class="btn primary">${editingBarangay?"Update Registration":"Save Registration"}</button></div></form></section>`;
}
function contractors(){
 const rows=store.contractors.map(c=>`<tr data-search="${esc((c.name+" "+c.license).toLowerCase())}"><td><b>${esc(c.name)}</b></td><td>${esc(c.license)}</td><td>${esc(c.phone)}</td><td>${esc(c.email)}</td><td>${badge(c.status)}</td><td><button class="btn small" data-c-action="view" data-id="${c.id}">View</button><button class="btn small" data-c-action="edit" data-id="${c.id}">Edit</button><button class="btn small ${c.status==="Inactive"?"":"danger"}" data-c-action="toggle" data-id="${c.id}">${c.status==="Inactive"?"Enable":"Disable"}</button></td></tr>`);
 const c=store.contractors.find(x=>x.id===editingContractor)||{}, summary=store.contractors.find(x=>x.id===editingContractor);
 const sum=summary?`<div class="statuses">${[["Assigned Collectors",summary.collectors],["Registered Vehicles",summary.vehicles],["Assigned Routes (via schedules)",summary.routes],["Completed Collection Runs",summary.runs]].map(([k,v])=>`<div class="status-row"><span>${k}</span><b>${esc(v)}</b></div>`).join("")}</div>`:`<p class="muted">Select a contractor to view the sample operational summary.</p>`;
 return heading("Manage Contractors","Maintain contractor registration and operational details")+
 `<div class="stats three">${stat("Registered Contractors",store.contractors.length,"♙")}${stat("Active Contractors",store.contractors.filter(x=>x.status==="Active").length,"✓")}${stat("Inactive Contractors",store.contractors.filter(x=>x.status==="Inactive").length,"◷")}</div>`+
 `<section class="panel"><div class="panel-head"><div><h2>Contractor Accounts</h2><p class="sub">Registered contractors and business information</p></div><div class="toolbar"><input id="search-c" aria-label="Search contractors" placeholder="Company or license number"><button id="new-c" class="btn primary">+ Register Contractor</button></div></div>${table(["Company Name","Business License","Contact Number","Email","Status","Actions"],rows)}</section>`+
 `<div class="two space"><section class="panel" id="contractor-form-panel"><div class="panel-head"><h2>Contractor Registration / Details</h2></div><form id="form-c"><div class="field-grid">${field("Company Name","name","text",c.name)}${field("Business License No.","license","text",c.license)}${field("Contact Number","phone","tel",c.phone)}${field("Email","email","email",c.email)}${field("Address","address","text",c.address||"Iloilo City")}${select("Status","status",["Active","Inactive"],c.status||"Active")}</div><div class="form-actions"><button type="button" class="btn" id="clear-c">Clear</button><button class="btn primary" type="submit">${editingContractor?"Update Contractor":"Save Contractor"}</button></div></form></section>${panel("Selected Contractor: Operational Summary",sum,"Demonstration figures; assignments are outside this GSO prototype")}</div>`;
}
function schedules(){
 const s=store.schedules.find(x=>x.id===editingSchedule)||{}, days=["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
 const routeOptions=routes.map(r=>[r.id,r.name]);
 const rows=store.schedules.map(x=>`<tr><td>SCH-${String(x.id).padStart(3,"0")}</td><td>${esc(rName(x.route))}</td><td>${esc(x.days.join(", "))}</td><td>${esc(x.from)}</td><td>${esc(x.until)}</td><td>${badge(x.status)}</td><td><button class="btn small" data-s-action="view" data-id="${x.id}">View</button><button class="btn small" data-s-action="edit" data-id="${x.id}">Edit</button><button class="btn small danger" data-s-action="delete" data-id="${x.id}">Delete</button></td></tr>`);
 const stops=routes.find(r=>r.id===selectedRoute)?.stops||[];
 return heading("Manage Schedules","Configure schedules using GSO-managed routes")+
 `<section class="panel"><div class="panel-head"><h2>Create / Edit Collection Schedule</h2></div><form id="form-s"><div class="field-grid three">${select("GSO-Managed Route","route",routeOptions,s.route||selectedRoute)}${field("Effective From","from","date",s.from||"")}${field("Effective Until","until","date",s.until||"")}</div><div class="field space"><label>Collection Days (schedule_day.day_of_week)</label><div class="days">${days.map(d=>`<label><input type="checkbox" name="days" value="${d}" ${(s.days||[]).includes(d)?"checked":""}>${d}</label>`).join("")}</div></div><div class="field-grid three">${select("Schedule Status","status",["Active","Inactive"],s.status||"Active")}</div><div class="form-actions"><button class="btn" type="button" id="clear-s">Clear</button><button class="btn primary" type="submit">${editingSchedule?"Update Schedule":"Save Schedule"}</button></div></form></section>`+
 `<section class="panel space"><div class="panel-head"><h2>Collection Schedule Records</h2><span class="pill active">${store.schedules.length} schedules</span></div>${table(["Schedule ID","Route Name","Collection Days","Effective From","Effective Until","Status","Actions"],rows)}</section>`+
 `<section class="panel space"><div class="panel-head"><div><h2>Route Stops — Selected Route</h2><p class="sub">Ordered stops are maintained under route_stop and linked to the selected GSO-managed route.</p></div><div style="min-width:190px">${select("Preview Route","preview-route",routeOptions,selectedRoute)}</div></div>${table(["Stop Order","Barangay","Stop / Location","Expected Arrival","Stop Status"],stops.map((st,i)=>`<tr><td>${i+1}</td><td>${esc(st.barangay)}</td><td>${esc(st.name)}</td><td>${esc(st.arrival)}</td><td>${badge(st.status)}</td></tr>`))}<p class="muted">Route stops shown are example data. Route creation is outside these five submitted wireframes.</p></section>`;
}
const categories=[
 ["Collection Performance","Scheduled, completed, delayed, missed","▥"],
 ["Contractor Performance","Assignments and collection outcomes","♙"],
 ["Route Performance","Route stops and completion rates","⌁"],
 ["Complaint Report","Complaints and resolution status","☷"],
 ["Vehicle Monitoring","Vehicles, GPS and assignment records","▣"]
];
function buildReport(type,from,to){
 const dateOK=d=>(!from||d>=from)&&(!to||d<=to);
 if(type==="Collection Performance")return store.operations.filter(o=>dateOK(o.date)).map(o=>({date:o.date,related:rName(o.route),status:o.status,detail:`${o.completed}/${o.stops} completed stops`}));
 if(type==="Contractor Performance")return store.operations.filter(o=>dateOK(o.date)).map(o=>({date:o.date,related:o.contractor,status:o.status,detail:rName(o.route)}));
 if(type==="Route Performance")return store.operations.filter(o=>dateOK(o.date)).map(o=>({date:o.date,related:rName(o.route),status:o.status,detail:`${o.completed}/${o.stops} stops`}));
 if(type==="Complaint Report")return store.complaints.filter(o=>dateOK(o.date)).map(o=>({date:o.date,related:o.related,status:o.status,detail:o.type}));
 return store.operations.filter(o=>dateOK(o.date)).map(o=>({date:o.date,related:o.vehicle,status:o.status,detail:`${o.contractor} — sample vehicle record`}));
}
function reports(){
 const complete=reportRows.filter(x=>x.status==="Completed"||x.status==="Resolved").length;
 const delayed=reportRows.filter(x=>x.status==="Delayed"||x.status==="Missed").length;
 const statusLabels=[...new Set(reportRows.map(x=>x.status))];
 const chart=reportGenerated?`<div class="chart"><b style="font-size:12px">Report results by status</b>${statusLabels.length?statusLabels.map(s=>{const n=reportRows.filter(x=>x.status===s).length;return `<div class="bar-row"><span>${esc(s)}</span><div class="bar-bg"><div class="bar" style="width:${n/Math.max(reportRows.length,1)*100}%"></div></div><b>${n}</b></div>`}).join(""):`<p class="muted">No matching records for the selected filters.</p>`}`:`<div class="tip">Select a report category and press Generate to preview results.</div>`;
 return heading("Manage Reports","Generate and export records from the prototype data")+
 `<section class="panel"><div class="panel-head"><h2>Generate and Export Reports</h2></div><form id="form-r"><div class="field-grid four">${select("Report Type","type",categories.map(x=>x[0]),category)}${field("From Date","from","date","",false)}${field("To Date","to","date","",false)}<div class="field"><label>Actions</label><div class="toolbar"><button class="btn primary" type="submit">Generate</button><button class="btn" type="button" id="export-r" ${reportGenerated?"":"disabled"}>Export CSV</button></div></div></div></form></section>`+
 `<div class="two space"><section class="panel"><div class="panel-head"><h2>Report Categories</h2></div><div class="cat-list">${categories.map(([name,desc,icon])=>`<button class="cat ${category===name?"selected":""}" data-category="${esc(name)}"><i>${icon}</i><span><strong>${name}</strong><small>${desc}</small></span></button>`).join("")}</div></section><section class="panel"><div class="panel-head"><h2>Selected Report Summary</h2></div><div class="mini-stats"><div class="mini"><small>Total Records</small><b>${reportGenerated?reportRows.length:"—"}</b></div><div class="mini"><small>Completed / Resolved</small><b>${reportGenerated?complete:"—"}</b></div><div class="mini"><small>Delayed / Missed</small><b>${reportGenerated?delayed:"—"}</b></div></div>${chart}</section></div>`+
 `<section class="panel space"><div class="panel-head"><h2>Report Records (Generated from System Data)</h2></div>${table(["Report Date","Report Type","Related Route / Contractor","Result / Status","Details"],reportGenerated?reportRows.map(x=>`<tr><td>${esc(x.date)}</td><td>${esc(category)}</td><td>${esc(x.related)}</td><td>${badge(x.status)}</td><td>${esc(x.detail)}</td></tr>`):[],reportGenerated?"No matching records.":"Choose filters and click Generate.")}</section>`;
}
function filterRows(input){const needle=input.value.toLowerCase().trim();const rows=input.closest(".panel").querySelectorAll("tbody tr[data-search]");rows.forEach(row=>row.hidden=!row.dataset.search.includes(needle))}
function bindPage(){
 if(page==="barangays"){
 $("#search-b").oninput=e=>filterRows(e.target);
 $("#new-b").onclick=()=>{editingBarangay=null;render();$("#barangay-form-panel").scrollIntoView({behavior:"smooth"})};
 $("#clear-b").onclick=()=>{editingBarangay=null;render()};
 $("#docs-b").onclick=()=>{const items=store.barangays.filter(b=>b.document);confirmBox("Supporting documentation",items.length?items.map(b=>b.name+": "+b.document).join("\n"):"No document filenames recorded yet. Files are not uploaded in this prototype.",()=>{})};
 document.querySelectorAll("[data-b-action]").forEach(btn=>btn.onclick=()=>{
 const b=store.barangays.find(x=>x.id===Number(btn.dataset.id));if(!b)return;
 if(btn.dataset.bAction==="toggle")return confirmBox("Change registration status",`Change ${b.name} to ${b.status==="Inactive"?"Active":"Inactive"}?`,()=>{b.status=b.status==="Inactive"?"Active":"Inactive";save();render();toast("Barangay status updated.")});
 editingBarangay=b.id;render();$("#barangay-form-panel").scrollIntoView({behavior:"smooth"});if(btn.dataset.bAction==="view")toast("Showing selected barangay details.");
 });
 $("#form-b").onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target),name=String(fd.get("name")).trim();if(!name)return toast("Enter barangay name.");
 const obj=store.barangays.find(x=>x.id===editingBarangay)||{id:Date.now()};for(const k of ["name","city","contact","phone","email","status"])obj[k]=String(fd.get(k)||"").trim();
 const file=e.target.querySelector("[name=document]").files[0];if(file)obj.document=file.name;
 if(!editingBarangay)store.barangays.push(obj);editingBarangay=null;save();render();toast("Barangay registration saved in browser.")};
 }
 if(page==="contractors"){
 $("#search-c").oninput=e=>filterRows(e.target);
 $("#new-c").onclick=()=>{editingContractor=null;render();$("#contractor-form-panel").scrollIntoView({behavior:"smooth"})};
 $("#clear-c").onclick=()=>{editingContractor=null;render()};
 document.querySelectorAll("[data-c-action]").forEach(btn=>btn.onclick=()=>{
 const c=store.contractors.find(x=>x.id===Number(btn.dataset.id));if(!c)return;
 if(btn.dataset.cAction==="toggle")return confirmBox("Change contractor status",`Change ${c.name} to ${c.status==="Inactive"?"Active":"Inactive"}?`,()=>{c.status=c.status==="Inactive"?"Active":"Inactive";save();render();toast("Contractor status updated.")});
 editingContractor=c.id;render();$("#contractor-form-panel").scrollIntoView({behavior:"smooth"});if(btn.dataset.cAction==="view")toast("Showing selected contractor details.");
 });
 $("#form-c").onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target),license=String(fd.get("license")).trim();
 if(store.contractors.some(c=>c.license.toLowerCase()===license.toLowerCase()&&c.id!==editingContractor))return toast("Business license already registered.");
 const obj=store.contractors.find(x=>x.id===editingContractor)||{id:Date.now(),collectors:0,vehicles:0,routes:0,runs:0};
 for(const k of ["name","license","phone","email","address","status"])obj[k]=String(fd.get(k)||"").trim();
 if(!editingContractor)store.contractors.push(obj);editingContractor=null;save();render();toast("Contractor saved in browser.")};
 }
 if(page==="schedules"){
 $("#preview-route").onchange=e=>{selectedRoute=e.target.value;render()};
 $("#form-s [name=route]").onchange=e=>{selectedRoute=e.target.value;const current=new FormData($("#form-s"));/* Route preview is updated when the user saves or selects preview. */};
 $("#clear-s").onclick=()=>{editingSchedule=null;render()};
 document.querySelectorAll("[data-s-action]").forEach(btn=>btn.onclick=()=>{
 const s=store.schedules.find(x=>x.id===Number(btn.dataset.id));if(!s)return;
 if(btn.dataset.sAction==="delete")return confirmBox("Delete schedule",`Delete schedule SCH-${s.id}? This removes it from this browser only.`,()=>{store.schedules=store.schedules.filter(x=>x.id!==s.id);if(editingSchedule===s.id)editingSchedule=null;save();render();toast("Schedule deleted.")});
 selectedRoute=s.route;editingSchedule=s.id;render();window.scrollTo({top:0,behavior:"smooth"});
 if(btn.dataset.sAction==="view")toast("Showing selected schedule.");
 });
 $("#form-s").onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target),days=fd.getAll("days");
 if(!days.length)return toast("Select at least one collection day.");
 if(fd.get("from")>fd.get("until"))return toast("Effective Until must not precede Effective From.");
 const route=String(fd.get("route"));if(!routes.some(r=>r.id===route))return toast("Select an existing route.");
 const obj=store.schedules.find(x=>x.id===editingSchedule)||{id:Math.max(0,...store.schedules.map(s=>s.id))+1};
 Object.assign(obj,{route,from:String(fd.get("from")),until:String(fd.get("until")),days,status:String(fd.get("status"))});
 if(!editingSchedule)store.schedules.push(obj);editingSchedule=null;selectedRoute=route;save();render();toast("Collection schedule saved in browser.")};
 }
 if(page==="reports"){
 document.querySelectorAll("[data-category]").forEach(btn=>btn.onclick=()=>{category=btn.dataset.category;reportGenerated=false;reportRows=[];render()});
 $("#form-r").onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target),from=String(fd.get("from")),to=String(fd.get("to"));if(from&&to&&from>to)return toast("To Date must not be before From Date.");category=String(fd.get("type"));reportRows=buildReport(category,from,to);reportGenerated=true;render();toast(`${reportRows.length} report records generated.`)};
 $("#export-r").onclick=()=>{if(!reportGenerated)return;const data=[["Report Date","Report Type","Related","Status","Details"],...reportRows.map(x=>[x.date,category,x.related,x.status,x.detail])];const csv=data.map(row=>row.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(",")).join("\r\n");const url=URL.createObjectURL(new Blob(["\ufeff"+csv],{type:"text/csv;charset=utf-8"}));const a=document.createElement("a");a.href=url;a.download="CleanDrive_"+category.replaceAll(" ","_")+"_Demo.csv";a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);toast("CSV exported.")};
 }
}
$("#nav").onclick=e=>{const b=e.target.closest("[data-page]");if(b)setPage(b.dataset.page)};
$("#menu").onclick=()=>$("#sidebar").classList.toggle("open");
$("#notify").onclick=()=>toast("No new notifications in the demo.");
$("#settings").onclick=()=>confirmBox("Prototype settings","Reset all demo data to its original sample values? This will erase edits made in this browser.",()=>{store=clone(initial);editingBarangay=editingContractor=editingSchedule=null;save();render();toast("Sample records restored.")});
$("#logout").onclick=()=>confirmBox("Log Out","End your current GSO session?",()=>{window.location.href='../../logout.php';});
const hash=location.hash.slice(1);if(titles[hash])page=hash;
render();

async function loadGsoTracking(){
 try{
  const response=await fetch(new URL('../../api/index.php',window.location.href)+"?action=gso_tracking",{credentials:"same-origin",headers:{"Accept":"application/json"}});
  const json=await response.json();
  if(!response.ok||json.error)throw Error(json.error||"Unable to load live collector locations.");
  gsoTracking=json.data||[];
  renderGsoTracking();
 }catch(error){
  const target=document.querySelector("#gso-live-map");
  if(target)target.innerHTML=`<p class="muted">${esc(error.message)}</p>`;
 }
}

function renderGsoTracking(){
 const target=document.querySelector("#gso-live-map");
 if(!target)return;
 const active=gsoTracking.filter(x=>x.latitude!==null&&x.longitude!==null&&x.recorded_at);
 if(!active.length){target.innerHTML='<div style="text-align:center"><h3>No live collector position</h3><p class="muted">Start GPS tracking from a Collector account.</p></div>';return}

 loadGoogleMaps().then(()=>{
  target.innerHTML='<div id="google-live-map" class="google-live-map"></div><div class="live-tracking-list" id="google-live-list"></div>';
  const center={lat:Number(active[0].latitude),lng:Number(active[0].longitude)};
  if(!googleMap){
   googleMap=new google.maps.Map(target.querySelector('#google-live-map'),{center,zoom:13,mapTypeControl:false,streetViewControl:false,fullscreenControl:true});
  }else{googleMap.setCenter(center)}

  const activeIds=new Set();
  active.forEach(item=>{
   const assignmentId=String(item.collection_assignment_id),position={lat:Number(item.latitude),lng:Number(item.longitude)};
   activeIds.add(assignmentId);
   let marker=googleMapMarkers.get(assignmentId);
   if(marker){marker.setPosition(position)}
   else{
    marker=new google.maps.Marker({position,map:googleMap,title:item.collector_name||'Collector'});
    googleMapMarkers.set(assignmentId,marker);
   }
   const speed=item.speed==null?'Unavailable':`${Number(item.speed).toFixed(1)} km/h`;
   const info=`<div><b>${esc(item.collector_name||'Unknown collector')}</b><br>${esc(item.plate_number||'No plate')} · ${esc(item.route_name||'Unknown route')}<br>Speed: ${esc(speed)}<br>Updated: ${esc(item.recorded_at)}</div>`;
   marker._cleandriveInfo=marker._cleandriveInfo||new google.maps.InfoWindow();
   marker._cleandriveInfo.setContent(info);
  });

  googleMapMarkers.forEach((marker,id)=>{if(!activeIds.has(id)){marker.setMap(null);googleMapMarkers.delete(id)}});
  const list=target.querySelector('#google-live-list');
  list.innerHTML=active.map(x=>`<button type="button" class="live-tracking-item" data-live-assignment="${x.collection_assignment_id}"><b>${esc(x.collector_name||'Unknown collector')}</b><small>${esc(x.plate_number||'No plate')} · ${esc(x.route_name||'Unknown route')}</small><small>${esc(x.recorded_at)}</small></button>`).join('');
  list.querySelectorAll('[data-live-assignment]').forEach(button=>button.onclick=()=>{
   const item=active.find(x=>String(x.collection_assignment_id)==button.dataset.liveAssignment);if(!item)return;
   const position={lat:Number(item.latitude),lng:Number(item.longitude)},marker=googleMapMarkers.get(button.dataset.liveAssignment);
   googleMap.panTo(position);googleMap.setZoom(15);if(marker){marker._cleandriveInfo.open({map:googleMap,anchor:marker})}
  });
 }).catch(error=>{
  renderOpenStreetMapFallback(target,gsoTracking.filter(x=>x.latitude!==null&&x.longitude!==null&&x.recorded_at));
 });
}

function loadGoogleMaps(){
 const config=window.CLEANDRIVE_GOOGLE_MAPS_CONFIG||{};
 if(!config.enabled||!config.apiKey)return Promise.reject(new Error('Google Maps API key is missing.'));
 if(window.google?.maps)return Promise.resolve(window.google);
 if(googleMapsLoading)return googleMapsLoading;
 googleMapsLoading=new Promise((resolve,reject)=>{
  const callback='cleandriveGoogleMapsReady';
  window[callback]=()=>resolve(window.google);
  const script=document.createElement('script');
  script.src=`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(config.apiKey)}&callback=${callback}&language=${encodeURIComponent(config.language||'en')}&region=${encodeURIComponent(config.region||'PH')}`;
  script.async=true;
  script.onerror=()=>reject(new Error('Google Maps failed to load. Check the API key, API restrictions, and billing.'));
  document.head.appendChild(script);
 });
 return googleMapsLoading;
}

function renderOpenStreetMapFallback(target, active){
 const first=active[0],lat=Number(first.latitude),lon=Number(first.longitude),delta=.012;
 target.innerHTML=`<iframe title="Collector fallback map" class="live-tracking-frame" src="https://www.openstreetmap.org/export/embed.html?bbox=${lon-delta},${lat-delta},${lon+delta},${lat+delta}&layer=mapnik&marker=${lat},${lon}" loading="lazy"></iframe><div class="live-tracking-list">${active.map(x=>`<button type="button" class="live-tracking-item" data-live-assignment="${x.collection_assignment_id}"><b>${esc(x.collector_name||'Unknown collector')}</b><small>${esc(x.plate_number||'No plate')} · ${esc(x.route_name||'Unknown route')}</small><small>${esc(x.recorded_at)}</small></button>`).join('')}</div>`;
 target.querySelectorAll('[data-live-assignment]').forEach(button=>button.onclick=()=>{
  const item=active.find(x=>String(x.collection_assignment_id)==button.dataset.liveAssignment);if(!item)return;
  const latitude=Number(item.latitude),longitude=Number(item.longitude),distance=.012;
  target.querySelector('iframe').src=`https://www.openstreetmap.org/export/embed.html?bbox=${longitude-distance},${latitude-distance},${longitude+distance},${latitude+distance}&layer=mapnik&marker=${latitude},${longitude}`;
 });
}
window.gm_authFailure=()=>{
 const target=document.querySelector('#gso-live-map');
 if(target&&typeof gsoTracking!=='undefined'&&gsoTracking.length){renderOpenStreetMapFallback(target,gsoTracking.filter(x=>x.latitude!==null&&x.longitude!==null&&x.recorded_at))}
};
