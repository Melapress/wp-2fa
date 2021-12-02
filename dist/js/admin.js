jQuery(document).ready((function(){if(jQuery("#excluded_sites_search").length){const t=`${wp2faData.ajaxURL}?action=get_all_network_sites&wp_2fa_nonce=${wp2faData.nonce}`;jQuery("#excluded_sites_search").autocomplete({source:t,minLength:1,focus:function(){return!1},select:function(e,t){const a=jQuery("#excluded_sites").val();a.includes(t.item.value)||jQuery("#excluded_sites").val(`${a+t.item.value},`);const n=jQuery("#excluded_sites").val().split(",");return jQuery("#excluded_sites_buttons").html(""),jQuery.each(n,(function(e){n[e]&&jQuery("#excluded_sites_buttons").append(`<a class="user-btn button button-secondary" data-user-value="${n[e]}">${n[e].split(":")[0]}<span class="remove-item">x</span></a>`)})),jQuery("#excluded_sites_search").val(""),!1},open:function(e,t){jQuery(".ui-menu-item").each((function(e,t){var a=jQuery(this).text();jQuery(this).text(a.split(":")[0])}))}});var e=jQuery("#excluded_sites").val().split(",");jQuery.each(e,(function(t){e[t]&&jQuery("#excluded_sites_buttons").append(`<a class="user-btn button button-secondary" data-user-value="${e[t]}">${e[t].split(":")[0]}<span class="remove-item">x</span></a>`)}))}jQuery("body").on("click",".remove-item",(function(e){e.preventDefault();var t=`${t=jQuery(this).closest(".user-btn").attr("data-user-value")},`,a=(a=jQuery(this).closest("div").siblings('input[type="hidden"]').val()).replace(t,"");jQuery(this).closest("div").siblings('input[type="hidden"]').val(a),jQuery(this).closest(".user-btn").remove()})),jQuery('[name="wp_2fa_policy[enforcement-policy]"], [name="wp_2fa_policy[grace-policy]"]').on("input",(function(){if("no-grace-period"==jQuery('input[name="wp_2fa_policy[grace-policy]"]:checked').val()&&"all-users"==jQuery('input[name="wp_2fa_policy[enforcement-policy]"]:checked').val()){var e=jQuery("[data-user-login-name]").attr("data-user-login-name"),t=jQuery("#excluded-users-multi-select");jQuery("#exclude-self-from-instant-2fa").length&&!jQuery(t).find('option[value="'+e+'"]').length&&MicroModal.show("exclude-self-from-instant-2fa")}if("all-users"!=jQuery('input[name="wp_2fa_policy[enforcement-policy]"]:checked').val()){var a=jQuery("#excluded-users-multi-select").select2("data"),n=jQuery("#excluded-roles-multi-select").select2("data");(jQuery(a).length||jQuery(n).length)&&MicroModal.show("warn-exclusions-will-be-removed")}"all-users"==jQuery('input[name="wp_2fa_policy[enforcement-policy]"]:checked').val()?jQuery("#exclusion_settings_wrapper").removeClass("disabled").slideDown(300):jQuery("#exclusion_settings_wrapper").slideUp(300).addClass("disabled")})),jQuery("body").on("click","[data-clear-exclusions]",(function(e){jQuery("#excluded-roles-multi-select, #excluded-users-multi-select").val(null).trigger("change")})),jQuery("body").on("click","#warn-exclusions-will-be-removed [data-cancel-action]",(function(e){jQuery('input[id="all-users"]').prop("checked",!0).trigger("click"),jQuery('[name="wp_2fa_policy[grace-policy]"]').trigger("input")})),jQuery("body").on("click","[data-user-login-name]",(function(e){e.preventDefault();var t=[],a=jQuery("[data-user-login-name]").attr("data-user-login-name");t.push(a);var n=jQuery("#excluded-users-multi-select");if(!jQuery(n).find('option[value="'+t+'"]').length){var r=new Option(t,t,!0,!0);jQuery(n).append(r).trigger("change")}})),jQuery("body").on("input",'input[type="number"]#grace-period',(function(e){var t=jQuery(this),a=t.val(),n=a&&0<=Math.abs(a)?Math.abs(a):null,r=t.attr("max");parseInt(r)<parseInt(n)&&(n=r),n!=a&&t.val(n)})),jQuery("body").on("focusout",'input[type="number"]#grace-period',(function(e){var t=jQuery(this),a=t.val(),n=t.attr("min");""===jQuery.trim(a)&&t.val(n)})),jQuery("body").on("click",'input[type="checkbox"]#grace-cron',(function(e){jQuery(this).is(":checked")?jQuery(".destory-session-setting").removeClass("disabled"):jQuery(this).is(":not(:checked)")&&(jQuery(".destory-session-setting").addClass("disabled"),jQuery('input[type="checkbox"]#destory-session').prop("checked",!1))})),jQuery('input[type="checkbox"]#grace-cron').is(":checked")?jQuery(".destory-session-setting").removeClass("disabled"):jQuery('input[type="checkbox"]#grace-cron').is(":not(:checked)")&&(jQuery(".destory-session-setting").addClass("disabled"),jQuery('input[type="checkbox"]#destory-session').prop("checked",!1)),jQuery("body").on("click",'input[type="radio"][id*="use_custom_page"]',(function(e){jQuery(this).attr("id").indexOf("dont")&&jQuery(this).is(":checked")?jQuery(this).closest("table").find(".custom-user-page-setting").removeClass("disabled"):jQuery(this).closest("table").find(".custom-user-page-setting").addClass("disabled")})),jQuery("body").on("click",'input[type="checkbox"][name*="enable_email"]',(function(e){jQuery(this).is(":checked")?jQuery(this).parent().parent().find("div.use-different-hotp-mail").removeClass("disabled"):jQuery(this).parent().parent().find("div.use-different-hotp-mail").addClass("disabled")})),jQuery("body").on("click",'input[type="checkbox"][name*="enable_trusted_devices"]',(function(e){jQuery(this).is(":checked")?jQuery(this).closest("table").find(".trusted-settings").removeClass("disabled"):jQuery(this).closest("table").find(".trusted-settings").addClass("disabled")})),jQuery("body").on("click",'input[type="checkbox"][name*="enable_oob_email"]',(function(e){jQuery(this).is(":checked")?jQuery(this).parent().parent().find("div.use-different-oob-mail").removeClass("disabled"):jQuery(this).parent().parent().find("div.use-different-oob-mail").addClass("disabled")})),jQuery("body").on("click",".js-button-test-email-trigger",(function(e){e.preventDefault();const t=jQuery(this),a=t.attr("data-email-id"),n=t.attr("data-nonce");t.append('<span class="spinner is-active"></span>');const r=t.find(".spinner");t.siblings(".notice").remove(),t.attr("disabled","disabled"),t.addClass("has-spinner"),jQuery.post(wp2faData.ajaxURL,{action:"wp2fa_test_email",email_id:a,_wpnonce:n}).done((function(e){let a="notice notice-after-button notice-";a+=e.success?"success":"error";var n=e.success?wp2faData.email_sent_success:wp2faData.email_sent_failure;"data"in e&&(n=e.data),t.after(`<span class="${a}">${n}</span>`),r.remove(),t.removeClass("has-spinner"),t.removeAttr("disabled")}))}))})),jQuery(document).ready((function(){function e(){if(jQuery("[data-step-title]").length){jQuery(".step-title-wrapper").remove(),jQuery(".wp2fa-setup-content").prepend('<div class="step-title-wrapper"></div>');var e=1;jQuery("[data-step-title]:not(.hidden)").each((function(){var t=jQuery(this).attr("data-step-title");jQuery(this).hasClass("active")?jQuery(".step-title-wrapper").append(`<span class="step-title active-step-title"><span>${e}</span> ${t}</span>`):jQuery(".step-title-wrapper").append(`<span class="step-title"><span>${e}</span> ${t}</span>`),e++}))}}function t(){let e=new URL(location.href),t=new URLSearchParams(e.search);t.delete("show"),location.replace(`${location.pathname}?${t}`)}MicroModal.init(),e(),jQuery("body").on("click",".step-title",(function(t){var a=jQuery(this).text().substr(2);jQuery("[data-step-title]:not(.hidden)").each((function(){jQuery(this);jQuery("[data-step-title]").removeClass("active"),jQuery(".step-title").removeClass("active-step-title");jQuery(this).attr("data-step-title");jQuery(`[data-step-title="${a}"]`).addClass("active")})),e()})),jQuery("[data-unhide-when-checked]").each((function(){if(jQuery(this).is(":checked")){const e=jQuery(this).attr("data-unhide-when-checked");jQuery(e).show(0)}})),jQuery("body").on("click",'[for="all-users"]',(function(t){jQuery(".step-setting-wrapper.hidden").removeClass("hidden").addClass("un-hidden"),e()})),jQuery("body").on("click",'[for="do-not-enforce"], [for="certain-roles-only"]',(function(t){jQuery(".step-setting-wrapper.un-hidden").removeClass("un-hidden").addClass("hidden"),e()})),jQuery("body").on("click",".modal__btn",(function(e){e.preventDefault()})),jQuery("body").on("keypress",".wp2fa-modal",(function(e){if("13"==(e.keyCode?e.keyCode:e.which))return!1})),jQuery(document).on("click","[data-open-configure-2fa-wizard]",(function(e){e.preventDefault(),wp2fa_fireWizard()})),jQuery(document).on("click",'.step-setting-wrapper.active .option-pill input[type="checkbox"]',(function(e){"backup-codes"===this.id||jQuery(this).hasClass("disabled")?jQuery(this).hasClass("disabled")?jQuery("#backup-codes").prop("checked",!1):window.backupCodes=jQuery("#backup-codes").prop("checked"):!0!==jQuery("#geek").prop("checked")&&!0!==jQuery("#basic").prop("checked")?(jQuery("#backup-codes").addClass("disabled"),jQuery("label[for='backup-codes']").addClass("disabled"),window.backupCodes=jQuery("#backup-codes").prop("checked"),jQuery("#backup-codes").prop("checked",!1),jQuery('[name="next_step_setting"]').length&&jQuery('[name="next_step_setting"]').addClass("disabled").attr("name","next_step_setting_disabled")):(jQuery("#backup-codes").removeClass("disabled"),jQuery("label[for='backup-codes']").removeClass("disabled"),"undefined"!==window.backupCodes&&jQuery("#backup-codes").prop("checked",window.backupCodes),jQuery('[name="next_step_setting_disabled"]').length&&jQuery('[name="next_step_setting_disabled"]').removeClass("disabled").attr("name","next_step_setting"))})),jQuery(document).on("click",'.2fa-method-select input[type="checkbox"]',(function(e){let t="global"===jQuery(this).data("role")?"":"-"+jQuery(this).data("role");if("backup-codes"+t===this.id||jQuery(this).hasClass("disabled"))jQuery(this).hasClass("disabled")?jQuery("#backup-codes"+t).prop("checked",!1):window.backupCodes=jQuery("#backup-codes"+t).prop("checked");else{let e=!1;!0!==jQuery("#totp"+t).prop("checked")&&!0!==jQuery("#hotp"+t).prop("checked")&&(e=!0,jQuery("#oob"+t).length&&!0===jQuery("#oob"+t).prop("checked")&&(e=!1)),e?(jQuery("#backup-codes"+t).addClass("disabled"),jQuery('label[for="backup-codes'+t+'"]').addClass("disabled"),window.backupCodes=jQuery("#backup-codes"+t).prop("checked"),jQuery("#backup-codes"+t).prop("checked",!1)):(jQuery("#backup-codes"+t).removeClass("disabled"),jQuery('label[for="backup-codes'+t+'"]').removeClass("disabled"),"undefined"!==window.backupCodes&&jQuery("#backup-codes"+t).prop("checked",window.backupCodes))}})),jQuery(document).on("click","[data-close-2fa-modal]",(function(e){e.preventDefault();var t=`#${jQuery(this).closest(".wp2fa-modal").attr("id")}`;jQuery(t).removeClass("is-open").attr("aria-hidden","true")})),jQuery(document).on("click","[data-close-2fa-modal-and-refresh]",(function(e){e.preventDefault();var a=`#${jQuery(this).closest(".wp2fa-modal").attr("id")}`;jQuery(a).removeClass("is-open").attr("aria-hidden","true"),t()})),jQuery(document).on("click","[data-validate-authcode-ajax]",(function(e){e.preventDefault();const a=jQuery(this);let n="validate_authcode_via_ajax",r=!1;jQuery("#wp-2fa-totp-authcode").length&&jQuery("#wp-2fa-totp-authcode").val().length&&(r=!0),void 0!==jQuery(this).data("oob-test")&&(n="validate_oob_authcode_via_ajax");const s=jQuery(this).attr("data-nonce");var i={};jQuery.each(jQuery(".wp-2fa-user-profile-form :input, .wp2fa-modal :input").serializeArray(),(function(e,t){i[t.name]=t.value}));window.location.href;jQuery.ajax({type:"POST",dataType:"json",url:wp2faData.ajaxURL,data:{action:n,form:i,_wpnonce:s},complete:function(e){if(!1===e.responseJSON.success&&jQuery(a).parent().find(".verification-response").html(`<span style="color:red">${e.responseJSON.data.error}</span>`),!0===e.responseJSON.success){jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper");let e=jQuery("#2fa-wizard-config-backup-codes");r&&(jQuery("#2fa-wizard-backup-methods").length?e=jQuery("#2fa-wizard-backup-methods"):jQuery("#2fa-wizard-email-backup-selected").length&&(e=jQuery("#2fa-wizard-email-backup-selected"))),jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper").removeClass("active"),jQuery(".wizard-step.active").removeClass("active"),jQuery(e).addClass("active"),jQuery(document).on("click","#select-backup-method",(function(e){e.preventDefault();var t=jQuery("input[name=backup_method_select]:checked");jQuery(".wizard-step.active").removeClass("active"),jQuery("#"+t.data("step")).addClass("active")})),jQuery(document).on("click",'[name="save_step"], [data-close-2fa-modal]',(function(){"redirectToUrl"in wp2faWizardData&&""!=jQuery.trim(wp2faWizardData.redirectToUrl)?window.location.replace(wp2faWizardData.redirectToUrl):t()}))}}})})),jQuery("body").on("click",'.contains-hidden-inputs input[type="radio"]',(function(e){if(!jQuery(this).hasClass("js-nested")&&(jQuery(this).closest(".contains-hidden-inputs").find(".hidden").hide(200),jQuery(this).is("[data-unhide-when-checked]"))){const e=jQuery(this).attr("data-unhide-when-checked");jQuery(this).is(":checked")&&jQuery(e).slideDown(200)}})),jQuery(document).on("click",".dismiss-user-configure-nag",(function(){const e=jQuery(this).closest(".notice");jQuery.ajax({url:wp2faData.ajaxURL,data:{action:"dismiss_nag"},complete:function(){jQuery(e).slideUp()}})})),jQuery(document).on("click",".dismiss-user-reconfigure-nag",(function(){const e=jQuery(this).closest(".notice");jQuery.ajax({url:wp2faData.ajaxURL,data:{action:"wp2fa_dismiss_reconfigure_nag"},complete:function(t){jQuery(e).slideUp()}})})),jQuery(document).on("click","[data-trigger-account-unlock]",(function(){const e=jQuery(this).attr("data-nonce"),t=jQuery(this).attr("data-account-to-unlock");jQuery.ajax({url:wp2faData.ajaxURL,data:{action:"unlock_account",user_id:t,wp_2fa_nonce:e}})})),jQuery(document).on("click",".remove-2fa",(function(e){e.preventDefault()})),jQuery(document).on("click",".modal__close",(function(e){e.preventDefault(),jQuery(this).parent().find("#notify-users").length&&(MicroModal.show("notify-users"),jQuery(".button-confirm").blur())})),jQuery(document).on("click touchend",".button-confirm",(function(e){e.preventDefault(),MicroModal.close("configure-2fa"),MicroModal.close("notify-users")})),jQuery(document).on("click touchend",".button-decline",(function(e){e.preventDefault()})),jQuery(document).on("click","#close-settings",(function(e){e.preventDefault(),MicroModal.close("notify-admin-settings-page"),window.location.replace(jQuery(this).data("redirect-url"))})),jQuery(document).on("click",".first-time-wizard",(function(e){e.preventDefault(),MicroModal.show("notify-admin-settings-page")})),jQuery(document).on("click","[data-trigger-remove-2fa]",(function(){const e=jQuery(this).attr("data-nonce"),t=jQuery(this).attr("data-user-id");jQuery.ajax({url:wp2faData.ajaxURL,data:{action:"remove_user_2fa",user_id:t,wp_2fa_nonce:e},complete:function(e){location.reload()}})})),jQuery(document).on("click","[data-submit-2fa-form]",(function(e){jQuery("#submit").click()})),jQuery(document).on("click","[data-trigger-setup-email]",(function(e){let t="send_authentication_setup_email";var a="";if(a=jQuery("#custom-email-address").val()?jQuery("#custom-email-address").val():jQuery("#use_wp_email").val(),void 0!==jQuery(this).data("oob-test")&&(t="send_authentication_oob_setup_email",jQuery("#custom-oob-email-address").val()&&(a=jQuery("#custom-oob-email-address").val())),jQuery(this).hasClass("resend-email-code"))var n=!0,r=jQuery(this).text();const s=jQuery(this).attr("data-user-id"),i=jQuery(this).attr("data-nonce"),c=jQuery(this);jQuery.ajax({type:"POST",dataType:"json",url:wp2faData.ajaxURL,data:{action:t,email_address:a,user_id:s,nonce:i},complete:function(e){},success:function(e){n&&(jQuery(c).find("span").fadeTo(100,0,(function(){jQuery(c).find("span").delay(100),jQuery(c).find("span").text(wp2faWizardData.codeReSentText),jQuery(c).find("span").fadeTo(100,1)})),setTimeout((function(){jQuery(c).find("span").fadeTo(100,0,(function(){jQuery(c).find("span").delay(100),jQuery(c).find("span").text(r),jQuery(c).find("span").fadeTo(100,1)}))}),2500))}})})),jQuery("body").on("click",'.button[name="next_step_setting"]',(function(t){t.preventDefault;const a=jQuery(this).closest(".step-setting-wrapper.active"),n=jQuery(a).nextAll("div:not(.hidden)").filter(":first");jQuery(a).removeClass("active"),jQuery(n).addClass("active"),e()})),jQuery(document).on("change",'[name="wp_2fa_enabled_methods"]',(function(e){var t=jQuery('[name="wp_2fa_enabled_methods"]:checked').val();jQuery(".2fa-choose-method[data-next-step]").attr("data-next-step",`2fa-wizard-${t}`)})),jQuery("body").on("click",'.button[data-name="next_step_setting_modal_wizard"]',(function(e){e.preventDefault;var t=jQuery(this).attr("data-next-step");if(t){jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper");const e=jQuery(`#${t}`);jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper").removeClass("active"),jQuery(".wizard-step.active").removeClass("active"),jQuery(e).addClass("active")}else{const e=jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper"),t=jQuery(e).next();jQuery(".wizard-step.active").removeClass("active"),jQuery(t).addClass("active")}})),jQuery("body").on("click",".button[data-trigger-generate-backup-codes]",(function(e){e.preventDefault();const t=jQuery(this).attr("data-nonce");jQuery.ajax({type:"POST",dataType:"json",url:wp2faData.ajaxURL,data:{action:"run_ajax_generate_json",_wpnonce:t},complete:function(e){jQuery("#backup-codes-wrapper").slideUp(0),jQuery(".wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper").empty();var t=(t=jQuery.parseJSON(e.responseText)).data.codes;jQuery.each(t,(function(e,t){jQuery(".wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper").append(`${t} </br>`)})),jQuery("#backup-codes-wrapper").slideDown(500),jQuery(".close-wizard-link").text(wp2faWizardData.readyText).fadeIn(50)}})})),jQuery("body").on("click",".button[data-trigger-reset-key]",(function(e){e.preventDefault(),jQuery(".qr-code-wrapper").length&&jQuery(".qr-code-wrapper").addClass("regenerating");jQuery(this).attr("data-trigger-reset-key");jQuery(this);const t=jQuery(this).attr("data-nonce"),a=jQuery(this).attr("data-user-id");jQuery.ajax({type:"POST",dataType:"json",url:wp2faData.ajaxURL,data:{action:"regenerate_authentication_key",_wpnonce:t,user_id:a},complete:function(e){jQuery(".change-2fa-confirm.hidden").length&&jQuery(".change-2fa-confirm.hidden").trigger("click"),jQuery(".app-key").length&&(jQuery("#wp-2fa-totp-qrcode").attr("src",e.responseJSON.data.qr),jQuery(".app-key").text(e.responseJSON.data.key),jQuery('[name="wp-2fa-totp-key"]').val(e.responseJSON.data.key),setTimeout((function(){jQuery(".qr-code-wrapper").removeClass("regenerating")}),500))}})})),jQuery("body").on("click",".button[data-trigger-backup-code-download]",(function(e){e.preventDefault();const t=jQuery(this).attr("data-user"),a=jQuery(this).attr("data-website-url");!function(e,t){const a=document.createElement("a");a.setAttribute("href",`data:text/plain;charset=utf-8,${encodeURIComponent(t)}`),a.setAttribute("download",e),a.style.display="none",document.body.appendChild(a),a.click(),document.body.removeChild(a)}("backup_codes.txt",`${wp2faWizardData.codesPreamble} ${t} on the website ${a}:\n\n`+jQuery(".active #backup-codes-wrapper").text().split(" ").join("\n"))})),jQuery("body").on("click",".button[data-trigger-print]",(function(e){e.preventDefault();const t=jQuery(this).attr("data-user-id"),a=jQuery(this).attr("data-website-url"),n=`${wp2faWizardData.codesPreamble} ${t} on the website ${a}:\n\n`,r=jQuery(".active #backup-codes-wrapper")[0],s=window.open("","Print-Window");s.document.open(),s.document.write(`<html><body onload="window.print()">${n}</br></br>${r.innerHTML}</body></html>`),s.document.close(),setTimeout((function(){s.close()}),10)})),jQuery(document).on("click","#custom-email-address",(function(){jQuery("#use_custom_email").prop("checked",!0)})),jQuery(document).on("click","[data-check-on-click]",(function(){const e=jQuery(this).attr("data-check-on-click");jQuery(e).prop("checked",!0)})),jQuery(document).on("click","[data-trigger-submit-form]",(function(e){e.preventDefault();jQuery(this).attr("data-trigger-submit-form");jQuery(".change-2fa-confirm").trigger("click")})),jQuery(document).on("click","[data-reload]",(function(e){t()})),jQuery('[name="wp_2fa_settings[enforcement-policy]"]').on("input",(function(){"all-users"==jQuery('input[name="wp_2fa_settings[enforcement-policy]"]:checked').val()?(jQuery('[data-step-title="Exclude users"]').removeClass("hidden"),e()):(jQuery('[data-step-title="Exclude users"]').addClass("hidden"),e())}))})),window.wp2fa_fireWizard=function(){jQuery(".verification-response span").remove(),jQuery("#configure-2fa .wizard-step.active, #configure-2fa .step-setting-wrapper.active").removeClass("active"),jQuery("#configure-2fa .wizard-step:first-of-type, #configure-2fa .step-setting-wrapper:first-of-type").addClass("active"),jQuery('.modal__content input:not([type="radio"]):not([type="hidden"])').val(""),MicroModal.show("configure-2fa"),jQuery("input#basic").is(":visible")?jQuery("input#basic").click():jQuery("input#geek").is(":visible")?jQuery("input#geek").click():jQuery("input#oob").length&&jQuery("input#oob").click(),jQuery('[name="wp_2fa_enabled_methods"]').change(),1===jQuery(".wizard-step.active .option-pill").length&&jQuery(".modal__btn.button.button-primary.2fa-choose-method").click()},jQuery((function(){const e=function(e,t){jQuery(e).select2({width:"resolve",ajax:{url:`${wp2faData.ajaxURL}?wp_2fa_nonce=${wp2faData.nonce}`,dataType:"json",delay:250,data:function(e){return{term:e.term,action:t}},processResults:function(e){const t=[];return e&&jQuery.each(e,(function(e,a){t.push({id:a.label,text:a.value})})),{results:t}},cache:!0},minimumInputLength:2})};jQuery("#excluded-users-multi-select").length&&e("#excluded-users-multi-select","get_all_users"),jQuery("#enforced_users-multi-select").length&&e("#enforced_users-multi-select","get_all_users"),jQuery("#excluded-roles-multi-select").length&&jQuery("#excluded-roles-multi-select").select2(),jQuery("#enforced-roles-multi-select").length&&jQuery("#enforced-roles-multi-select").select2(),jQuery("#excluded-sites-multi-select").length&&e("#excluded-sites-multi-select","get_all_network_sites")}));
