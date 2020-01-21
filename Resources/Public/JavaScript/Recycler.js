/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
define(["require","exports","jquery","nprogress","TYPO3/CMS/Backend/ActionButton/DeferredAction","TYPO3/CMS/Backend/Modal","TYPO3/CMS/Backend/Notification","TYPO3/CMS/Backend/Severity","TYPO3/CMS/Backend/Input/Clearable"],(function(e,t,a,s,n,l,i,r){"use strict";var o;!function(e){e.searchForm="#recycler-form",e.searchText="#recycler-form [name=search-text]",e.searchSubmitBtn="#recycler-form button[type=submit]",e.depthSelector="#recycler-form [name=depth]",e.tableSelector="#recycler-form [name=pages]",e.recyclerTable="#itemsInRecycler",e.paginator="#recycler-index nav",e.reloadAction="a[data-action=reload]",e.massUndo="button[data-action=massundo]",e.massDelete="button[data-action=massdelete]",e.toggleAll=".t3js-toggle-all"}(o||(o={}));class c{constructor(){this.elements={},this.paging={currentPage:1,totalPages:1,totalItems:0,itemsPerPage:TYPO3.settings.Recycler.pagingSize},this.markedRecordsForMassAction=[],this.allToggled=!1,this.handleCheckboxSelects=e=>{const t=a(e.currentTarget),s=t.parents("tr"),n=s.data("table")+":"+s.data("uid");if(t.prop("checked"))this.markedRecordsForMassAction.push(n),s.addClass("warning");else{const e=this.markedRecordsForMassAction.indexOf(n);e>-1&&this.markedRecordsForMassAction.splice(e,1),s.removeClass("warning")}if(this.markedRecordsForMassAction.length>0){this.elements.$massUndo.hasClass("disabled")&&this.elements.$massUndo.removeClass("disabled").removeAttr("disabled"),this.elements.$massDelete.hasClass("disabled")&&this.elements.$massDelete.removeClass("disabled").removeAttr("disabled");const e=this.createMessage(TYPO3.lang["button.undoselected"],[this.markedRecordsForMassAction.length]),t=this.createMessage(TYPO3.lang["button.deleteselected"],[this.markedRecordsForMassAction.length]);this.elements.$massUndo.find("span.text").text(e),this.elements.$massDelete.find("span.text").text(t)}else this.resetMassActionButtons()},this.deleteRecord=e=>{if(TYPO3.settings.Recycler.deleteDisable)return;const t=a(e.currentTarget).parents("tr"),s="TBODY"!==t.parent().prop("tagName");let i,o;if(s)i=this.markedRecordsForMassAction,o=TYPO3.lang["modal.massdelete.text"];else{const e=t.data("uid"),a=t.data("table"),s=t.data("recordtitle");i=[a+":"+e],o="pages"===a?TYPO3.lang["modal.deletepage.text"]:TYPO3.lang["modal.deletecontent.text"],o=this.createMessage(o,[s,"["+i[0]+"]"])}l.confirm(TYPO3.lang["modal.delete.header"],o,r.error,[{text:TYPO3.lang["button.cancel"],btnClass:"btn-default",trigger:function(){l.dismiss()}},{text:TYPO3.lang["button.delete"],btnClass:"btn-danger",action:new n(()=>Promise.resolve(this.callAjaxAction("delete",i,s)))}])},this.undoRecord=e=>{const t=a(e.currentTarget).parents("tr"),s="TBODY"!==t.parent().prop("tagName");let i,o,c;if(s)i=this.markedRecordsForMassAction,o=TYPO3.lang["modal.massundo.text"],c=!0;else{const e=t.data("uid"),a=t.data("table"),s=t.data("recordtitle");i=[a+":"+e],o=(c="pages"===a)?TYPO3.lang["modal.undopage.text"]:TYPO3.lang["modal.undocontent.text"],o=this.createMessage(o,[s,"["+i[0]+"]"]),c&&t.data("parentDeleted")&&(o+=TYPO3.lang["modal.undo.parentpages"])}let d=null;d=c?a("<div />").append(a("<p />").text(o),a("<div />",{class:"checkbox"}).append(a("<label />").append(TYPO3.lang["modal.undo.recursive"]).prepend(a("<input />",{id:"undo-recursive",type:"checkbox"})))):a("<p />").text(o),l.confirm(TYPO3.lang["modal.undo.header"],d,r.ok,[{text:TYPO3.lang["button.cancel"],btnClass:"btn-default",trigger:function(){l.dismiss()}},{text:TYPO3.lang["button.undo"],btnClass:"btn-success",action:new n(()=>Promise.resolve(this.callAjaxAction("undo","object"==typeof i?i:[i],s,d.find("#undo-recursive").prop("checked"))))}])},a(()=>{this.initialize()})}static refreshPageTree(){top.TYPO3&&top.TYPO3.Backend&&top.TYPO3.Backend.NavigationContainer&&top.TYPO3.Backend.NavigationContainer.PageTree&&top.TYPO3.Backend.NavigationContainer.PageTree.refreshTree()}getElements(){this.elements={$searchForm:a(o.searchForm),$searchTextField:a(o.searchText),$searchSubmitBtn:a(o.searchSubmitBtn),$depthSelector:a(o.depthSelector),$tableSelector:a(o.tableSelector),$recyclerTable:a(o.recyclerTable),$tableBody:a(o.recyclerTable).find("tbody"),$paginator:a(o.paginator),$reloadAction:a(o.reloadAction),$massUndo:a(o.massUndo),$massDelete:a(o.massDelete),$toggleAll:a(o.toggleAll)}}registerEvents(){this.elements.$searchForm.on("submit",e=>{e.preventDefault(),""!==this.elements.$searchTextField.val()&&this.loadDeletedElements()}),this.elements.$searchTextField.on("keyup",e=>{""!==a(e.currentTarget).val()?this.elements.$searchSubmitBtn.removeClass("disabled"):(this.elements.$searchSubmitBtn.addClass("disabled"),this.loadDeletedElements())}),this.elements.$searchTextField.get(0).clearable({onClear:()=>{this.elements.$searchSubmitBtn.addClass("disabled"),this.loadDeletedElements()}}),this.elements.$depthSelector.on("change",()=>{a.when(this.loadAvailableTables()).done(()=>{this.loadDeletedElements()})}),this.elements.$tableSelector.on("change",()=>{this.paging.currentPage=1,this.loadDeletedElements()}),this.elements.$recyclerTable.on("click","[data-action=undo]",this.undoRecord),this.elements.$recyclerTable.on("click","[data-action=delete]",this.deleteRecord),this.elements.$reloadAction.on("click",e=>{e.preventDefault(),a.when(this.loadAvailableTables()).done(()=>{this.loadDeletedElements()})}),this.elements.$paginator.on("click","a[data-action]",e=>{e.preventDefault();const t=a(e.currentTarget);let s=!1;switch(t.data("action")){case"previous":this.paging.currentPage>1&&(this.paging.currentPage--,s=!0);break;case"next":this.paging.currentPage<this.paging.totalPages&&(this.paging.currentPage++,s=!0);break;case"page":this.paging.currentPage=parseInt(t.find("span").text(),10),s=!0}s&&this.loadDeletedElements()}),TYPO3.settings.Recycler.deleteDisable?this.elements.$massDelete.remove():this.elements.$massDelete.show(),this.elements.$recyclerTable.on("show.bs.collapse hide.bs.collapse","tr.collapse",e=>{let t,s,n=a(e.currentTarget).prev("tr").find("[data-action=expand]").find(".t3-icon");switch(e.type){case"show":t="t3-icon-pagetree-collapse",s="t3-icon-pagetree-expand";break;case"hide":t="t3-icon-pagetree-expand",s="t3-icon-pagetree-collapse"}n.removeClass(t).addClass(s)}),this.elements.$toggleAll.on("click",()=>{this.allToggled=!this.allToggled,a('input[type="checkbox"]').prop("checked",this.allToggled).trigger("change")}),this.elements.$recyclerTable.on("change","tr input[type=checkbox]",this.handleCheckboxSelects),this.elements.$massUndo.on("click",this.undoRecord),this.elements.$massDelete.on("click",this.deleteRecord)}initialize(){s.configure({parent:".module-loading-indicator",showSpinner:!1}),this.getElements(),this.registerEvents(),TYPO3.settings.Recycler.depthSelection>0?this.elements.$depthSelector.val(TYPO3.settings.Recycler.depthSelection).trigger("change"):a.when(this.loadAvailableTables()).done(()=>{this.loadDeletedElements()})}resetMassActionButtons(){this.markedRecordsForMassAction=[],this.elements.$massUndo.addClass("disabled").attr("disabled",!0),this.elements.$massUndo.find("span.text").text(TYPO3.lang["button.undo"]),this.elements.$massDelete.addClass("disabled").attr("disabled",!0),this.elements.$massDelete.find("span.text").text(TYPO3.lang["button.delete"])}loadAvailableTables(){return a.ajax({url:TYPO3.settings.ajaxUrls.recycler,dataType:"json",data:{action:"getTables",startUid:TYPO3.settings.Recycler.startUid,depth:this.elements.$depthSelector.find("option:selected").val()},beforeSend:()=>{s.start(),this.elements.$tableSelector.val(""),this.paging.currentPage=1},success:e=>{const t=[];this.elements.$tableSelector.children().remove(),a.each(e,(e,s)=>{const n=s[0],l=s[1],i=(s[2]?s[2]:TYPO3.lang.label_allrecordtypes)+" ("+l+")";t.push(a("<option />").val(n).text(i))}),t.length>0&&(this.elements.$tableSelector.append(t),""!==TYPO3.settings.Recycler.tableSelection&&this.elements.$tableSelector.val(TYPO3.settings.Recycler.tableSelection))},complete:()=>{s.done()}})}loadDeletedElements(){return a.ajax({url:TYPO3.settings.ajaxUrls.recycler,dataType:"json",data:{action:"getDeletedRecords",depth:this.elements.$depthSelector.find("option:selected").val(),startUid:TYPO3.settings.Recycler.startUid,table:this.elements.$tableSelector.find("option:selected").val(),filterTxt:this.elements.$searchTextField.val(),start:(this.paging.currentPage-1)*this.paging.itemsPerPage,limit:this.paging.itemsPerPage},beforeSend:()=>{s.start(),this.resetMassActionButtons()},success:e=>{this.elements.$tableBody.html(e.rows),this.buildPaginator(e.totalItems)},complete:()=>{s.done()}})}callAjaxAction(e,t,n,l=!1){let r={records:t,action:""},o=!1;if("undo"===e)r.action="undoRecords",r.recursive=l?1:0,o=!0;else{if("delete"!==e)return;r.action="deleteRecords"}return a.ajax({url:TYPO3.settings.ajaxUrls.recycler,type:"POST",dataType:"json",data:r,beforeSend:()=>{s.start()},success:e=>{e.success?i.success("",e.message):i.error("",e.message),this.paging.currentPage=1,a.when(this.loadAvailableTables()).done(()=>{this.loadDeletedElements(),n&&this.resetMassActionButtons(),o&&c.refreshPageTree(),this.allToggled=!1})},complete:()=>{s.done()}})}createMessage(e,t){return void 0===e?"":e.replace(/\{([0-9]+)\}/g,(function(e,a){return t[a]}))}buildPaginator(e){if(0===e)return void this.elements.$paginator.contents().remove();if(this.paging.totalItems=e,this.paging.totalPages=Math.ceil(e/this.paging.itemsPerPage),1===this.paging.totalPages)return void this.elements.$paginator.contents().remove();const t=a("<ul />",{class:"pagination pagination-block"}),s=[],n=a("<li />").append(a("<a />",{"data-action":"previous"}).append(a("<span />",{class:"t3-icon fa fa-arrow-left"}))),l=a("<li />").append(a("<a />",{"data-action":"next"}).append(a("<span />",{class:"t3-icon fa fa-arrow-right"})));1===this.paging.currentPage&&n.disablePagingAction(),this.paging.currentPage===this.paging.totalPages&&l.disablePagingAction();for(let e=1;e<=this.paging.totalPages;e++){const t=a("<li />",{class:this.paging.currentPage===e?"active":""});t.append(a("<a />",{"data-action":"page"}).append(a("<span />").text(e))),s.push(t)}t.append(n,s,l),this.elements.$paginator.html(t)}}return a.fn.disablePagingAction=function(){a(this).addClass("disabled").find(".t3-icon").unwrap().wrap(a("<span />"))},new c}));