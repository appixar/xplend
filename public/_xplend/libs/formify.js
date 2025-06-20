const formify = {
    conf: {
        appendErrorAfterParent: false,
        scrollToError: true
    },
    init: function (config = {}) {
        formify.conf = config;
        formify.mask.init();
        formify.validator.init();
        document.addEventListener("DOMContentLoaded", function () {
            var button = document.querySelector(".formify-submit");
            if (button) {
                button.addEventListener("click", function (event) {
                    // Seu código para lidar com o clique vai aqui
                    console.log("Botão .formify-submit foi clicado!");
                });
            }
        });
    },
    isInvalid: function (selector) {
        const form = document.querySelector(selector);
        const errorMessages = form.querySelectorAll('.error-input');
        return errorMessages.length > 0;
    },
    isValid: function (selector) {
        return !this.isInvalid(selector); // Retorna true se não houver erros e false se houver
    },
    checkForm: function (form) {
        const inputs = form.querySelectorAll('input, select');
        let allValid = true;
        inputs.forEach(input => {
            if (isVisible(input)) {
                if (!formify.validator.validateInput(input)) {
                    allValid = false;
                }
            }
        });
        return allValid;
    },
    check: function (formSelector) {
        const form = document.querySelector(formSelector);
        if (!form) {
            console.error('Formulário não encontrado!');
            return false;
        }
        return formify.checkForm(form);
    },
    mask: {
        formatInput: function (input, maskType) {
            let value = input.tagName === 'INPUT' ? input.value : input.textContent; // Corrigido para ler corretamente o valor/textContent baseado no tipo de elemento
            if (!value) return;

            // alphanumeric exceptions
            let exceptions = [];
            if (maskType === 'alphanumeric' && input.getAttribute('mask-except')) {
                exceptions = [...input.getAttribute('mask-except')]; // Transforma a string em um array de caracteres
            }

            // switch mask types
            switch (maskType) {
                case 'cpf':
                    value = formify.mask.formatCPF(value);
                    break;
                case 'cep':
                    value = formify.mask.formatCEP(value);
                    break;
                case 'cnpj':
                    value = formify.mask.formatCNPJ(value);
                    break;
                case 'date':
                    value = formify.mask.formatDate(value, 'YYYY-mm-dd');
                    break;
                case 'date-br':
                    value = formify.mask.formatDate(value, 'dd/mm/YYYY');
                    break;
                case 'phone':
                    value = formify.mask.formatPhone(value);
                    break;
                case 'phone-ddd':
                    value = formify.mask.formatPhoneWithArea(value);
                    break;
                case 'money':
                    value = formify.mask.formatCurrency(value);
                    break;
                case 'alphanumeric':
                    value = this.formatAlphanumeric(value, exceptions);
                    break;
                case 'time':
                    value = formify.mask.formatTime(value);
                    break;
                case 'time-sec':
                    value = formify.mask.formatTimeWithSec(value);
                    break;
            }
            if (input.tagName === 'INPUT') input.value = value;
            else input.textContent = value; // Atualizar elementos não-input
        },
        formatCPF: function (cpf) {
            return cpf.replace(/\D/g, '').slice(0, 11)
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        },
        formatCNPJ: function (cnpj) {
            return cnpj.replace(/\D/g, '').slice(0, 14)
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        },
        formatDate: function (date, format) {
            date = date.replace(/\D/g, '').slice(0, 8);
            if (format === 'YYYY-mm-dd') {
                return date.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
            } else if (format === 'dd/mm/YYYY') {
                return date.replace(/(\d{2})(\d{2})(\d{4})/, '$1/$2/$3');
            }
        },
        formatCEP: function (cep) {
            cep = cep.replace(/\D/g, ''); // Remove tudo o que não é dígito
            cep = cep.substring(0, 8); // Limita o comprimento a 8 dígitos

            // Insere o hífen após o quinto dígito
            if (cep.length >= 6) {
                cep = cep.replace(/^(\d{5})(\d{1,3})/, '$1-$2');
            }

            return cep;
        },
        formatPhone: function (phone) {
            return phone.replace(/\D/g, '').slice(0, 9)
                .replace(/(\d{5})(\d)/, '$1-$2');
        },
        formatPhoneWithArea: function (phone) {
            phone = phone.replace(/\D/g, '').slice(0, 11);
            const areaCode = phone.slice(0, 2);
            const firstPart = phone.slice(2, 7);
            const secondPart = phone.slice(7, 11);
            return `(${areaCode}) ${firstPart}-${secondPart}`;
        },
        formatCurrency: function (value) {
            value = parseFloat(value.replace(/\D/g, '')) / 100;
            return value.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        formatAlphanumeric: function (value, exceptions = []) {
            const escapedExceptions = exceptions.map(e => `\\${e}`).join('');
            return value.replace(new RegExp(`[^a-z0-9${escapedExceptions}]`, 'gi'), '').toLowerCase();
        },
        formatTime: function (time) {
            time = time.replace(/\D/g, '').slice(0, 4);
            return time.replace(/(\d{2})(\d{2})/, '$1:$2');
        },
        formatTimeWithSec: function (time) {
            time = time.replace(/\D/g, '').slice(0, 6);
            return time.replace(/(\d{2})(\d{2})(\d{2})/, '$1:$2:$3');
        },
        formatDateAgo: function (dateStr, maskRule = null) {
            // Verificar se a data não foi fornecida ou é inválida
            if (!dateStr || isNaN(Date.parse(dateStr))) {
                return ""; // Retorna string vazia se a data não for válida
            }
            const now = new Date();
            const date = new Date(dateStr);
            const diff = now - date;
            const seconds = Math.abs(diff / 1000);
            const minutes = Math.abs(diff / (60 * 1000));
            const hours = Math.abs(diff / (60 * 60 * 1000));
            const days = Math.abs(diff / (24 * 60 * 60 * 1000));
            const weeks = Math.abs(diff / (7 * 24 * 60 * 60 * 1000));
            const months = Math.abs(diff / (30 * 24 * 60 * 60 * 1000));

            const prepend = maskRule === 'no-prep' ? '' : (diff > 0 ? 'há ' : 'em ');

            const pluralize = (amount, singular, plural) => amount === 1 ? singular : plural;

            if (months >= 1) {
                const roundedMonths = Math.floor(months);
                return `${prepend}${roundedMonths} ${pluralize(roundedMonths, 'mês', 'meses')}`;
            } else if (weeks >= 1) {
                const roundedWeeks = Math.floor(weeks);
                return `${prepend}${roundedWeeks} ${pluralize(roundedWeeks, 'semana', 'semanas')}`;
            } else if (days >= 1) {
                const roundedDays = Math.floor(days);
                return `${prepend}${roundedDays} ${pluralize(roundedDays, 'dia', 'dias')}`;
            } else if (hours >= 1) {
                return `${prepend}${Math.floor(hours)}h ${Math.floor(minutes % 60)}min`;
            } else if (minutes >= 1) {
                return `${prepend}${Math.floor(minutes)} ${pluralize(minutes, 'min', 'min')}`;
            } else {
                return diff > 0 ? '1 min' : '1 min';
            }
        },
        initDateAgo: function () {
            const dateAgoElements = document.querySelectorAll('[mask="dateago"]');
            dateAgoElements.forEach(element => {
                const dateStr = element.textContent || element.innerText;
                const maskRule = element.getAttribute('mask-rule');
                const friendlyDate = formify.mask.formatDateAgo(dateStr, maskRule);
                element.textContent = friendlyDate;
            });
        },
        init: function () {
            const maskedInputs = document.querySelectorAll('input[mask], div[mask], span[mask], p[mask], td[mask]');
            maskedInputs.forEach(input => {
                const maskType = input.getAttribute('mask');
                formify.mask.formatInput(input, maskType);
                if (input.tagName === 'INPUT') {
                    input.addEventListener('input', () => formify.mask.formatInput(input, maskType)); // Evento de input apenas para inputs
                }
            });
            // Adicionar inicialização de 'dateago'
            formify.mask.initDateAgo();
        }
    },
    validator: {
        validateInput: function (input) {
            const isSelect = input.tagName === 'SELECT';
            const maskType = input.getAttribute('mask');
            const type = input.getAttribute('type');
            const isRequired = input.hasAttribute('required');
            const minLength = input.getAttribute('minlength');
            const isSecurePassword = input.classList.contains('secure-password');

            // Validar o atributo required (modificado para funcionar com select)
            if (isRequired) {
                if (isSelect) {
                    // Para select, verificamos se o valor é vazio ou o valor da primeira opção (geralmente um placeholder)
                    if (!input.value || (input.options.length > 0 && input.selectedIndex === 0 && input.options[0].value === '')) {
                        formify.validator.setError(input, 'Campo obrigatório.');
                        return false;
                    }
                } else if (!input.value.trim()) {
                    // Para outros elementos
                    formify.validator.setError(input, 'Campo obrigatório.');
                    return false;
                }
            }

            // O restante da validação permanece inalterado e se aplica apenas a inputs
            if (!isSelect) {
                // Validar o atributo required
                if (isRequired && !input.value.trim()) {
                    formify.validator.setError(input, 'Campo obrigatório.');
                    return false;
                }
                // Validar senha segura
                if (isSecurePassword && input.value && !formify.validator.validateSecurePassword(input.value)) {
                    formify.validator.setError(input, 'Senha insegura. Deve conter letras maiúsculas, minúsculas, números, caracteres especiais e no mínimo 6 caracteres.');
                    return false;
                }
                // Validar o atributo type
                if (type) {
                    if (type === 'email' && input.value && !formify.validator.validateEmail(input.value)) {
                        formify.validator.setError(input, 'Email inválido.');
                        return false;
                    } else if (type === 'url' && input.value && !formify.validator.validateURL(input.value)) {
                        formify.validator.setError(input, 'URL inválida.');
                        return false;
                    }
                }
                // Validar minlength
                if (minLength && input.value.length < parseInt(minLength)) {
                    formify.validator.setError(input, `A entrada deve ter no mínimo ${minLength} caracteres.`);
                    return false;
                }
                // Validar igualdade de campos com equal-to
                const equalToSelector = input.getAttribute('equal-to');
                if (equalToSelector) {
                    const equalToElement = document.querySelector(equalToSelector);
                    if (!formify.validator.validateEqualTo(input, equalToElement)) {
                        formify.validator.setError(input, 'Os campos não são iguais.');
                        return false;
                    }
                }
                // Validar máscaras
                if (maskType && input.value.trim() && !formify.validator.validateMask(input, maskType)) {
                    let customMessage = '';
                    switch (maskType) {
                        case 'cpf':
                            customMessage = 'CPF inválido.';
                            break;
                        case 'cnpj':
                            if (!formify.validator.validateCNPJ(input.value)) {
                                customMessage = 'CNPJ inválido.';
                            }
                            break;
                        case 'date':
                            customMessage = 'Data inválida.';
                            break;
                        case 'date-br':
                            customMessage = 'Data inválida.';
                            break;
                        case 'phone':
                            customMessage = 'Número de telefone inválido.';
                            break;
                        case 'phone-ddd':
                            customMessage = 'Número de telefone com DDD inválido.';
                            break;
                        case 'money':
                            customMessage = 'Formato monetário inválido.';
                            break;
                        case 'alphanumeric':
                            customMessage = 'A entrada deve ser alfanumérica e em minúsculas.';
                            break;
                        case 'time':
                        case 'time-sec':
                            customMessage = 'Horário inválido.';
                            break;
                    }
                    formify.validator.setError(input, customMessage);
                    return false;
                }
            }
            // Limpar erro
            formify.validator.clearError(input);
            return true;
        },
        validateEqualTo: function (input, equalToElement) {
            return input.value === equalToElement.value;
        },
        validateSecurePassword: function (password) {
            let hasLowercase = false;
            let hasUppercase = false;
            let hasDigit = false;
            let hasSpecialCharacter = false;

            const specialCharacters = "@*-=.,%#!?:+_/$~";

            for (let char of password) {
                if ('a' <= char && char <= 'z') {
                    hasLowercase = true;
                } else if ('A' <= char && char <= 'Z') {
                    hasUppercase = true;
                } else if ('0' <= char && char <= '9') {
                    hasDigit = true;
                } else if (specialCharacters.includes(char)) {
                    hasSpecialCharacter = true;
                }
            }
            return hasLowercase && hasUppercase && hasDigit && hasSpecialCharacter && (password.length >= 6);
        },
        validateURL: function (url) {
            const regex = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
            return regex.test(url);
        },
        validateCNPJ: function (cnpj) {
            cnpj = cnpj.replace(/[^\d]+/g, '');

            if (cnpj == '') return false;

            if (cnpj.length != 14)
                return false;

            // Elimina CNPJs invalidos conhecidos
            if (cnpj == "00000000000000" ||
                cnpj == "11111111111111" ||
                cnpj == "22222222222222" ||
                cnpj == "33333333333333" ||
                cnpj == "44444444444444" ||
                cnpj == "55555555555555" ||
                cnpj == "66666666666666" ||
                cnpj == "77777777777777" ||
                cnpj == "88888888888888" ||
                cnpj == "99999999999999")
                return false;

            // Valida DVs
            let tamanho = cnpj.length - 2
            let numeros = cnpj.substring(0, tamanho);
            let digitos = cnpj.substring(tamanho);
            let soma = 0;
            let pos = tamanho - 7;

            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2)
                    pos = 9;
            }

            let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

            if (resultado != digitos.charAt(0))
                return false;

            tamanho = tamanho + 1;
            numeros = cnpj.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;

            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2)
                    pos = 9;
            }

            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

            if (resultado != digitos.charAt(1))
                return false;

            return true;
        },
        validateEmail: function (email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        validateCPF: function (cpf) {
            cpf = cpf.replace(/\D/g, '');

            if (cpf.length !== 11) return false;
            if (/^(.)\1{10}$/.test(cpf)) return false; // verifica padrões repetidos como "11111111111"

            for (let j = 0; j < 2; j++) {
                let sum = 0;
                for (let i = 0; i < 9 + j; i++) {
                    sum += cpf[i] * ((10 + j) - i);
                }

                let checkDigit = 11 - (sum % 11);
                if (checkDigit >= 10) checkDigit = 0;

                if (checkDigit !== Number(cpf[9 + j])) return false;
            }
            return true;
        },
        validateTime: function (time) {
            const regex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            return regex.test(time);
        },
        validateTimeWithSec: function (time) {
            const regex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/;
            return regex.test(time);
        },
        validateMask: function (input, maskType) {
            const value = input.value;
            let regex;

            switch (maskType) {
                case 'cpf':
                    regex = /^\d{3}\.\d{3}\.\d{3}\-\d{2}$/;
                    return regex.test(value) && formify.validator.validateCPF(value);
                case 'cnpj':
                    regex = /^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$/;
                    if (regex.test(value)) {
                        return formify.validator.validateCNPJ(value);
                    } else {
                        return false;
                    }
                case 'date':
                    regex = /^\d{4}-\d{2}-\d{2}$/;
                    if (regex.test(value)) {
                        const [, year, month, day] = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        return formify.validator.isValidDate(+day, +month, +year);
                    } else {
                        return false;
                    }
                case 'date-br':
                    regex = /^\d{2}\/\d{2}\/\d{4}$/;
                    if (regex.test(value)) {
                        const [, day, month, year] = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                        return formify.validator.isValidDate(+day, +month, +year);
                    } else {
                        return false;
                    }
                case 'phone':
                    regex = /^\d{5}-\d{4}$/;
                    break;
                case 'phone-ddd':
                    regex = /^\(\d{2}\) \d{5}-\d{4}$/;
                    break;
                case 'money':
                    regex = /^\d{1,3}(\.\d{3})*,\d{2}$/;
                    break;
                case 'alphanumeric':
                    regex = /^[a-z0-9]+$/;
                    break;
                case 'time':
                    regex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
                    return regex.test(value) && formify.validator.validateTime(value);
                case 'time-sec':
                    regex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/;
                    if (regex.test(value)) {
                        return formify.validator.validateTimeWithSec(value);
                    } else {
                        return false;
                    }
                case 'cep':
                    regex = /^\d{5}-\d{3}$/;
                    break;
                default:
                    return false;
            }

            return regex ? regex.test(value) : false;
        },

        isLeapYear: function (year) {
            return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
        },
        isValidDate: function (day, month, year) {
            if (month < 1 || month > 12) return false;

            let daysInMonth;
            switch (month) {
                case 2:
                    daysInMonth = formify.validator.isLeapYear(year) ? 29 : 28;
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    daysInMonth = 30;
                    break;
                default:
                    daysInMonth = 31;
            }

            return day > 0 && day <= daysInMonth;
        },
        setError: function (input, message) {
            input.classList.add('error-input');
            if (input.classList.contains('no-error')) {
                return; // Encerra a função se o input tiver a classe .no-error
            }

            let errorMessageElement;
            if (formify.conf.appendErrorAfterParent) {
                errorMessageElement = input.parentElement.nextElementSibling;
            } else {
                errorMessageElement = input.nextElementSibling;
            }

            if (!errorMessageElement || errorMessageElement.className !== 'error-message') {
                errorMessageElement = document.createElement('div');
                errorMessageElement.className = 'error-message';
                if (formify.conf.appendErrorAfterParent) {
                    input.parentElement.insertAdjacentElement('afterend', errorMessageElement);
                } else {
                    input.after(errorMessageElement);
                }
            }
            errorMessageElement.innerText = message;
            // Deslizar o scroll até o elemento com erro
            if (formify.conf.scrollToError) errorMessageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        clearError: function (input) {
            input.classList.remove('error-input');
            let errorMessageElement;
            if (formify.conf.appendErrorAfterParent) {
                errorMessageElement = input.parentElement.nextElementSibling;
            } else {
                errorMessageElement = input.nextElementSibling;
            }

            if (errorMessageElement && errorMessageElement.className === 'error-message') {
                errorMessageElement.remove();
            }
        },
        init: function () {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function (event) {
                    if (!formify.checkForm(form)) {
                        event.preventDefault();
                    }
                });
                const formElements = form.querySelectorAll('input, select');
                formElements.forEach(element => {
                    // Adicionado listener de blur para mostrar erros após saída do campo
                    element.addEventListener('blur', function () {
                        formify.validator.validateInput(element);
                    });

                    // Adicionado listener para limpar erro em tempo real
                    if (element.tagName === 'INPUT') {
                        element.addEventListener('input', function () {
                            if (element.classList.contains('error-input')) {
                                formify.validator.validateInput(element);
                            }
                        });
                    } else if (element.tagName === 'SELECT') {
                        element.addEventListener('change', function () {
                            if (element.classList.contains('error-input')) {
                                formify.validator.validateInput(element);
                            }
                        });
                    }
                });
            });
        }
    }
}
//
// CHECK UNSAVED FORM CHANGES,
// ... THEN CONFIRM BEFORE LEAVE PAGE
//
var formChanged = false;
var forms = document.querySelectorAll('.unsaved-changes');
forms.forEach(function (form) {
    form.addEventListener('input', function () { formChanged = true; });
    form.addEventListener('submit', function () { formChanged = false; });
});
window.addEventListener('beforeunload', function (event) {
    if (formChanged) {
        var confirmationMessage = 'It looks like you have been editing something. If you leave before saving, your changes will be lost.';
        (event || window.event).returnValue = confirmationMessage; // Cross-browser compatibility (for IE)
        return confirmationMessage;
    }
});
function isVisible(element) {
    return element.offsetParent !== null;
}