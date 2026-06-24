var KraboLoginCodeForm

const KraboLoginWithCodeFocus = (form) => {
    var input = document.querySelectorAll(".code");
    index = form.dataset.krabotoken.length;
    input[index].focus();
};

//Start
const KraboLoginWithCodeInitCodeInputs = (form) => {
    var input = document.querySelectorAll(".code");
    var inputField = document.getElementById("codefield");
    var submitButton = document.getElementById("validateToken");
    var inputCount = 0;
    var inputFieldCount = 0;

    form.dataset.krabotoken = '';
    input.forEach((element) => {
        element.value = "";
    });
    if (inputField) {
        KraboLoginWithCodeFocus(form);
    }
    input.forEach((element) => {
        inputFieldCount++;
        element.addEventListener("keyup", (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, "");
            let { value } = e.target;

            if (value.length == 1) {
                KraboLoginWithCodeFocus(form);
                if (inputCount <= (inputFieldCount -1) && e.key != "Backspace") {
                    form.dataset.krabotoken += value;
                    if (inputCount < (inputFieldCount -1)) {
                        KraboLoginWithCodeFocus(form);
                    }
                }
                inputCount += 1;
            } else if (value.length == 0 && e.key == "Backspace") {
                form.dataset.krabotoken = form.dataset.krabotoken.substring(0, form.dataset.krabotoken.length - 1);
                if (inputCount == 0) {
                    KraboLoginWithCodeFocus(form);
                    return false;
                }
                KraboLoginWithCodeFocus(form);
                e.target.previousElementSibling.value = "";
                KraboLoginWithCodeFocus(form);
                inputCount -= 1;
            } else if (value.length > 1) {
                e.target.value = value.split("")[0];
            }
        });
    });
    if (inputFieldCount > 0) {
        window.addEventListener("keyup", (e) => {
            if (inputCount > (inputFieldCount -1)) {
                submitButton.classList.remove("hide");
                submitButton.classList.add("show");
                if (e.key == "Backspace") {
                    form.dataset.krabotoken = form.dataset.krabotoken.substring(0, form.dataset.krabotoken.length - 1);
                    KraboLoginWithCodeFocus(form);
                    inputField.lastElementChild.value = "";
                    inputCount -= 1;
                    submitButton.classList.add("hide");
                } else {
                    submitButton.click();
                }
            }
        });
    }
};

function KraboLoginWithCodeSubmitFunction(func) {
    if (document.getElementById('password')) {
        document.getElementById('password').required = false;
    }
    if (document.getElementById('ctrl_password')) {
        document.getElementById('ctrl_password').required = false;
    }
    if (document.getElementById('ctrl_password_confirm')) {
        document.getElementById('ctrl_password_confirm').required = false;
    }
    if (document.querySelectorAll("[required]", KraboLoginCodeForm).length) {
        document.querySelectorAll("[required]", KraboLoginCodeForm).forEach((element) => {
            element.required = false;
        });
    }
    document.getElementById('function').value = func;
    document.getElementById('KraboLoginAjaxSubmitButton').click();
    return false;
}

function KraboLoginWithCodeRequest(form, body, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('Accept', 'text/html');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-Contao-Ajax-Form', form.querySelector('[name="FORM_SUBMIT"]').value);
    xhr.setRequestHeader('X-Krabo-Token', form.dataset.krabotoken);

    form.ariaBusy = 'true';
    form.dataset.ajaxForm = 'loading';

    xhr.onload = () => {
        form.ariaBusy = 'false';
        form.dataset.ajaxForm = '';
        callback(xhr);
    };

    xhr.send(body || null)
}

function KraboLoginWithCodeInitForm(form) {
    KraboLoginCodeForm = form;
    form.addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(form);

        // Send the triggered button data as well
        if (e.submitter) {
            formData.append(e.submitter.name, e.submitter.value);

            // Prevent double form submission
            e.submitter.disabled = true;
            setTimeout(() => e.submitter.disabled = false, 30000);
        }

        KraboLoginWithCodeRequest(form, formData, xhr => {
            const location = xhr.getResponseHeader('X-Ajax-Location');

            // Handle the redirect header
            if (location) {
                window.location.href = location;
                return;
            }

            const range = document.createRange();
            range.selectNode(form.parentNode);

            const newForm = range.createContextualFragment(xhr.responseText).firstElementChild;
            form.replaceWith(newForm);

            if (!newForm.getAttribute('action')) {
                newForm.action = xhr.responseURL;
            }

            KraboLoginWithCodeInitForm(newForm);
        });
    });
    KraboLoginWithCodeInitCodeInputs(form);
}