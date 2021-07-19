jQuery(document).ready((function() {
    function e() {
        if (jQuery("[data-step-title]").length) {
            jQuery(".step-title-wrapper").remove(),
            jQuery(".wp2fa-setup-content").prepend('<div class="step-title-wrapper"></div>');
            var e = 1;
            jQuery("[data-step-title]:not(.hidden)").each((function() {
                var t = jQuery(this).attr("data-step-title");
                jQuery(this).hasClass("active") ? jQuery(".step-title-wrapper").append(`<span class="step-title active-step-title"><span>${e}</span> ${t}</span>`) : jQuery(".step-title-wrapper").append(`<span class="step-title"><span>${e}</span> ${t}</span>`),
                e++
            }
            ))
        }
    }
    function t() {
        let e = new URL(location.href)
          , t = new URLSearchParams(e.search);
        t.delete("show"),
        location.replace(`${location.pathname}?${t}`)
    }
    MicroModal.init(),
    e(),
    jQuery("body").on("click", ".step-title", (function(t) {
        var a = jQuery(this).text().substr(2);
        jQuery("[data-step-title]:not(.hidden)").each((function() {
            jQuery(this);
            jQuery("[data-step-title]").removeClass("active"),
            jQuery(".step-title").removeClass("active-step-title");
            jQuery(this).attr("data-step-title");
            jQuery(`[data-step-title="${a}"]`).addClass("active")
        }
        )),
        e()
    }
    )),
    jQuery("[data-unhide-when-checked]").each((function() {
        if (jQuery(this).is(":checked")) {
            const e = jQuery(this).attr("data-unhide-when-checked");
            jQuery(e).show(0)
        }
    }
    )),
    jQuery("body").on("click", '[for="all-users"], [for="certain-roles-only"]', (function(t) {
        jQuery(".step-setting-wrapper.hidden").removeClass("hidden").addClass("un-hidden"),
        e()
    }
    )),
    jQuery("body").on("click", '[for="do-not-enforce"]', (function(t) {
        jQuery(".step-setting-wrapper.un-hidden").removeClass("un-hidden").addClass("hidden"),
        e()
    }
    )),
    jQuery("body").on("click", ".modal__btn", (function(e) {
        e.preventDefault()
    }
    )),
    jQuery("body").on("keypress", ".wp2fa-modal", (function(e) {
        if ("13" == (e.keyCode ? e.keyCode : e.which))
            return !1
    }
    )),
    jQuery(document).on("click", "[data-open-configure-2fa-wizard]", (function(e) {
        e.preventDefault(),
        jQuery(".verification-response span").remove(),
        jQuery("#configure-2fa .wizard-step.active, #configure-2fa .step-setting-wrapper.active").removeClass("active"),
        jQuery("#configure-2fa .wizard-step:first-of-type, #configure-2fa .step-setting-wrapper:first-of-type").addClass("active"),
        jQuery('.modal__content input:not([type="radio"]):not([type="hidden"])').val(""),
        MicroModal.show("configure-2fa"),
        jQuery("input#basic").is(":visible") ? jQuery("input#basic").click() : jQuery("input#geek").click(),
        jQuery('[name="wp_2fa_enabled_methods"]').change(),
        1 === jQuery(".wizard-step.active .option-pill").length && jQuery(".modal__btn.button.button-primary.2fa-choose-method").click()
    }
    )),
    jQuery(document).on("click", '.step-setting-wrapper.active .option-pill input[type="checkbox"]', (function(e) {
        "backup-codes" === this.id || jQuery(this).hasClass("disabled") ? jQuery(this).hasClass("disabled") ? jQuery("#backup-codes").prop("checked", !1) : window.backupCodes = jQuery("#backup-codes").prop("checked") : !0 !== jQuery("#geek").prop("checked") && !0 !== jQuery("#basic").prop("checked") ? (jQuery("#backup-codes").addClass("disabled"),
        jQuery("label[for='backup-codes']").addClass("disabled"),
        window.backupCodes = jQuery("#backup-codes").prop("checked"),
        jQuery("#backup-codes").prop("checked", !1),
        jQuery('[name="next_step_setting"]').length && jQuery('[name="next_step_setting"]').addClass("disabled").attr("name", "next_step_setting_disabled")) : (jQuery("#backup-codes").removeClass("disabled"),
        jQuery("label[for='backup-codes']").removeClass("disabled"),
        "undefined" !== window.backupCodes && jQuery("#backup-codes").prop("checked", window.backupCodes),
        jQuery('[name="next_step_setting_disabled"]').length && jQuery('[name="next_step_setting_disabled"]').removeClass("disabled").attr("name", "next_step_setting"))
    }
    )),
    jQuery(document).on("click", '#2fa-method-select input[type="checkbox"]', (function(e) {
        "backup-codes" === this.id || jQuery(this).hasClass("disabled") ? jQuery(this).hasClass("disabled") ? jQuery("#backup-codes").prop("checked", !1) : window.backupCodes = jQuery("#backup-codes").prop("checked") : !0 !== jQuery("#totp").prop("checked") && !0 !== jQuery("#hotp").prop("checked") ? (jQuery("#backup-codes").addClass("disabled"),
        jQuery("label[for='backup-codes']").addClass("disabled"),
        window.backupCodes = jQuery("#backup-codes").prop("checked"),
        jQuery("#backup-codes").prop("checked", !1)) : (jQuery("#backup-codes").removeClass("disabled"),
        jQuery("label[for='backup-codes']").removeClass("disabled"),
        "undefined" !== window.backupCodes && jQuery("#backup-codes").prop("checked", window.backupCodes))
    }
    )),
    jQuery(document).on("click", "[data-close-2fa-modal]", (function(e) {
        e.preventDefault();
        var t = `#${jQuery(this).closest(".wp2fa-modal").attr("id")}`;
        jQuery(t).removeClass("is-open").attr("aria-hidden", "true")
    }
    )),
    jQuery(document).on("click", "[data-close-2fa-modal-and-refresh]", (function(e) {
        e.preventDefault();
        var a = `#${jQuery(this).closest(".wp2fa-modal").attr("id")}`;
        jQuery(a).removeClass("is-open").attr("aria-hidden", "true"),
        t()
    }
    )),
    jQuery(document).on("click", "[data-validate-authcode-ajax]", (function(e) {
        e.preventDefault();
        const a = jQuery(this)
          , n = jQuery(this).attr("data-nonce");
        var r = {};
        jQuery.each(jQuery(".wp-2fa-user-profile-form :input, .wp2fa-modal :input").serializeArray(), (function(e, t) {
            r[t.name] = t.value
        }
        ));
        window.location.href;
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: wp2faData.ajaxURL,
            data: {
                action: "validate_authcode_via_ajax",
                form: r,
                _wpnonce: n
            },
            complete: function(e) {
                if (!1 === e.responseJSON.success && jQuery(a).parent().find(".verification-response").html(`<span style="color:red">${e.responseJSON.data.error}</span>`),
                !0 === e.responseJSON.success) {
                    jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper");
                    const e = jQuery("#2fa-wizard-config-backup-codes");
                    jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper").removeClass("active"),
                    jQuery(".wizard-step.active").removeClass("active"),
                    jQuery(e).addClass("active"),
                    jQuery(document).on("click", '[name="save_step"], [data-close-2fa-modal]', (function() {
                        "redirectToUrl"in wp2faWizardData && "" != jQuery.trim(wp2faWizardData.redirectToUrl) ? window.location.replace(wp2faWizardData.redirectToUrl) : t()
                    }
                    ))
                }
            }
        })
    }
    )),
    jQuery("body").on("click", '.contains-hidden-inputs input[type="radio"]', (function(e) {
        if (!jQuery(this).hasClass("js-nested") && (jQuery(this).closest(".contains-hidden-inputs").find(".hidden").hide(200),
        jQuery(this).is("[data-unhide-when-checked]"))) {
            const e = jQuery(this).attr("data-unhide-when-checked");
            jQuery(this).is(":checked") && jQuery(e).slideDown(200)
        }
    }
    )),
    jQuery(document).on("click", ".dismiss-user-configure-nag", (function() {
        const e = jQuery(this).closest(".notice");
        jQuery.ajax({
            url: wp2faData.ajaxURL,
            data: {
                action: "dismiss_nag"
            },
            complete: function() {
                jQuery(e).slideUp()
            }
        })
    }
    )),
    jQuery(document).on("click", ".dismiss-user-reconfigure-nag", (function() {
        const e = jQuery(this).closest(".notice");
        jQuery.ajax({
            url: wp2faData.ajaxURL,
            data: {
                action: "wp2fa_dismiss_reconfigure_nag"
            },
            complete: function(t) {
                jQuery(e).slideUp()
            }
        })
    }
    )),
    jQuery(document).on("click", "[data-trigger-account-unlock]", (function() {
        const e = jQuery(this).attr("data-nonce")
          , t = jQuery(this).attr("data-account-to-unlock");
        jQuery.ajax({
            url: wp2faData.ajaxURL,
            data: {
                action: "unlock_account",
                user_id: t,
                wp_2fa_nonce: e
            }
        })
    }
    )),
    jQuery(document).on("click", ".remove-2fa", (function(e) {
        e.preventDefault()
    }
    )),
    jQuery(document).on("click", ".modal__close", (function(e) {
        e.preventDefault(),
        jQuery(this).parent().find("#notify-users").length && (MicroModal.show("notify-users"),
        jQuery(".button-confirm").blur())
    }
    )),
    jQuery(document).on("click touchend", ".button-confirm", (function(e) {
        e.preventDefault(),
        MicroModal.close("configure-2fa"),
        MicroModal.close("notify-users")
    }
    )),
    jQuery(document).on("click touchend", ".button-decline", (function(e) {
        e.preventDefault()
    }
    )),
    jQuery(document).on("click", "#close-settings", (function(e) {
        e.preventDefault(),
        MicroModal.close("notify-admin-settings-page"),
        window.location.replace(jQuery(this).data("redirect-url"))
    }
    )),
    jQuery(document).on("click", ".first-time-wizard", (function(e) {
        e.preventDefault(),
        MicroModal.show("notify-admin-settings-page")
    }
    )),
    jQuery(document).on("click", "[data-trigger-remove-2fa]", (function() {
        const e = jQuery(this).attr("data-nonce")
          , t = jQuery(this).attr("data-user-id");
        jQuery.ajax({
            url: wp2faData.ajaxURL,
            data: {
                action: "remove_user_2fa",
                user_id: t,
                wp_2fa_nonce: e
            },
            complete: function(e) {
                location.reload()
            }
        })
    }
    )),
    jQuery(document).on("click", "[data-submit-2fa-form]", (function(e) {
        jQuery("#submit").click()
    }
    )),
    jQuery(document).on("click", "[data-trigger-setup-email]", (function(e) {
        if (jQuery("#custom-email-address").val())
            var t = jQuery("#custom-email-address").val();
        else
            t = jQuery("#use_wp_email").val();
        if (jQuery(this).hasClass("resend-email-code"))
            var a = !0
              , n = jQuery(this).text();
        const r = jQuery(this).attr("data-user-id")
          , i = jQuery(this).attr("data-nonce")
          , o = jQuery(this);
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: wp2faData.ajaxURL,
            data: {
                action: "send_authentication_setup_email",
                email_address: t,
                user_id: r,
                nonce: i
            },
            complete: function(e) {},
            success: function(e) {
                a && (jQuery(o).find("span").fadeTo(100, 0, (function() {
                    jQuery(o).find("span").delay(100),
                    jQuery(o).find("span").text(wp2faWizardData.codeReSentText),
                    jQuery(o).find("span").fadeTo(100, 1)
                }
                )),
                setTimeout((function() {
                    jQuery(o).find("span").fadeTo(100, 0, (function() {
                        jQuery(o).find("span").delay(100),
                        jQuery(o).find("span").text(n),
                        jQuery(o).find("span").fadeTo(100, 1)
                    }
                    ))
                }
                ), 2500))
            }
        })
    }
    )),
    jQuery("body").on("click", '.button[name="next_step_setting"]', (function(t) {
        t.preventDefault;
        const a = jQuery(this).closest(".step-setting-wrapper.active")
          , n = jQuery(a).nextAll("div:not(.hidden)").filter(":first");
        jQuery(a).removeClass("active"),
        jQuery(n).addClass("active"),
        e()
    }
    )),
    jQuery(document).on("change", '[name="wp_2fa_enabled_methods"]', (function(e) {
        var t = jQuery('[name="wp_2fa_enabled_methods"]:checked').val();
        jQuery(".2fa-choose-method[data-next-step]").attr("data-next-step", `2fa-wizard-${t}`)
    }
    )),
    jQuery("body").on("click", '.button[data-name="next_step_setting_modal_wizard"]', (function(e) {
        e.preventDefault;
        var t = jQuery(this).attr("data-next-step");
        if (t) {
            jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper");
            const e = jQuery(`#${t}`);
            jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper").removeClass("active"),
            jQuery(".wizard-step.active").removeClass("active"),
            jQuery(e).addClass("active")
        } else {
            const e = jQuery(this).parent().parent().find(".active").not(".step-setting-wrapper")
              , t = jQuery(e).next();
            jQuery(".wizard-step.active").removeClass("active"),
            jQuery(t).addClass("active")
        }
    }
    )),
    jQuery("body").on("click", ".button[data-trigger-generate-backup-codes]", (function(e) {
        e.preventDefault();
        const t = jQuery(this).attr("data-nonce")
          , a = jQuery(this).attr("data-user-id");
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: wp2faData.ajaxURL,
            data: {
                action: "run_ajax_generate_json",
                _wpnonce: t,
                user_id: a
            },
            complete: function(e) {
                jQuery("#backup-codes-wrapper").slideUp(0),
                jQuery(".wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper").empty();
                var t = (t = jQuery.parseJSON(e.responseText)).data.codes;
                jQuery.each(t, (function(e, t) {
                    jQuery(".wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper").append(`${t} </br>`)
                }
                )),
                jQuery("#backup-codes-wrapper").slideDown(500),
                jQuery(".close-wizard-link").text(wp2faWizardData.readyText).fadeIn(50)
            }
        })
    }
    )),
    jQuery("body").on("click", ".button[data-trigger-reset-key]", (function(e) {
        e.preventDefault(),
        jQuery(".qr-code-wrapper").length && jQuery(".qr-code-wrapper").addClass("regenerating");
        jQuery(this).attr("data-trigger-reset-key");
        jQuery(this);
        const t = jQuery(this).attr("data-nonce")
          , a = jQuery(this).attr("data-user-id");
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: wp2faData.ajaxURL,
            data: {
                action: "regenerate_authentication_key",
                _wpnonce: t,
                user_id: a
            },
            complete: function(e) {
                jQuery(".change-2fa-confirm.hidden").length && jQuery(".change-2fa-confirm.hidden").trigger("click"),
                jQuery(".app-key").length && (jQuery("#wp-2fa-totp-qrcode").attr("src", e.responseJSON.data.qr),
                jQuery(".app-key").text(e.responseJSON.data.key),
                jQuery('[name="wp-2fa-totp-key"]').val(e.responseJSON.data.key),
                setTimeout((function() {
                    jQuery(".qr-code-wrapper").removeClass("regenerating")
                }
                ), 500))
            }
        })
    }
    )),
    jQuery("body").on("click", ".button[data-trigger-backup-code-download]", (function(e) {
        e.preventDefault();
        const t = jQuery(this).attr("data-user")
          , a = jQuery(this).attr("data-website-url");
        !function(e, t) {
            const a = document.createElement("a");
            a.setAttribute("href", `data:text/plain;charset=utf-8,${encodeURIComponent(t)}`),
            a.setAttribute("download", e),
            a.style.display = "none",
            document.body.appendChild(a),
            a.click(),
            document.body.removeChild(a)
        }("backup_codes.txt", `${wp2faWizardData.codesPreamble} ${t} on the website ${a}:\n\n` + jQuery(".active #backup-codes-wrapper").text().split(" ").join("\n"))
    }
    )),
    jQuery("body").on("click", ".button[data-trigger-print]", (function(e) {
        e.preventDefault();
        const t = jQuery(this).attr("data-user-id")
          , a = jQuery(this).attr("data-website-url")
          , n = `${wp2faWizardData.codesPreamble} ${t} on the website ${a}:\n\n`
          , r = jQuery(".active #backup-codes-wrapper")[0]
          , i = window.open("", "Print-Window");
        i.document.open(),
        i.document.write(`<html><body onload="window.print()">${n}</br></br>${r.innerHTML}</body></html>`),
        i.document.close(),
        setTimeout((function() {
            i.close()
        }
        ), 10)
    }
    )),
    jQuery(document).on("click", "#custom-email-address", (function() {
        jQuery("#use_custom_email").prop("checked", !0)
    }
    )),
    jQuery(document).on("click", "[data-check-on-click]", (function() {
        const e = jQuery(this).attr("data-check-on-click");
        jQuery(e).prop("checked", !0)
    }
    )),
    jQuery(document).on("click", "[data-trigger-submit-form]", (function(e) {
        e.preventDefault();
        jQuery(this).attr("data-trigger-submit-form");
        jQuery(".change-2fa-confirm").trigger("click")
    }
    )),
    jQuery(document).on("click", "[data-reload]", (function(e) {
        t()
    }
    )),
    jQuery('[name="wp_2fa_settings[enforcement-policy]"]').on("input", (function() {
        "all-users" == jQuery('input[name="wp_2fa_settings[enforcement-policy]"]:checked').val() ? (jQuery('[data-step-title="Exclude users"]').removeClass("hidden"),
        e()) : (jQuery('[data-step-title="Exclude users"]').addClass("hidden"),
        e())
    }
    ))
}
));
