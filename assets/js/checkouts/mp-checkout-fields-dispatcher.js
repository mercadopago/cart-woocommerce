/* eslint-disable no-unused-vars */
/**
 * Esta classe é responsável por gerenciar e disparar eventos customizados quando
 * os primeiros campos de checkouts que possuem campos, são preenchidos pelos usuários.
 * Suporta checkouts de PSE, YAPE, Ticket, Super Token e Cartão de Crédito.
 */
class MPCheckoutFieldsDispatcher {
    static EVENT_NAME_PREFIX = 'mp_checkout_field_';

    /**
     * Adiciona um listener de evento que dispara um evento customizado quando acionado.
     * 
     * Este método oferece três modos de operação:
     * 1. **Modo padrão**: Adiciona event listener padrão do DOM
     * 2. **Modo CardForm**: Usa o método `.on()` do SDK do MercadoPago para campos de cartão
     * 3. **Modo dispatch-only**: Apenas dispara o evento sem adicionar listener
     * 
     * @static
     * @method addEventListenerDispatcher
     * 
     * @param {HTMLElement|Object|null} fieldElementReference - Referência ao elemento DOM ou campo do CardForm.
     *                                                          Pode ser null quando onlyDispatch=true
     * @param {string} listenerEventName - Nome do evento a ser ouvido (ex: "focusout", "blur", "change")
     * @param {string} dispatchEventName - Nome do evento customizado a ser disparado (sem o prefixo)
     * @param {Object} [options={}] - Opções de configuração
     * @param {Function} [options.dispatchOnlyIf=(e) => true] - Função que determina se o evento deve ser disparado.
     *                                                          Recebe o evento original como parâmetro
     * @param {boolean} [options.isUsingCardForm=false] - Indica se está usando campos do CardForm do SDK
     * @param {boolean} [options.onlyDispatch=false] - Se true, apenas dispara o evento sem adicionar listener
     * 
     * @fires CustomEvent#mp_checkout_field_{dispatchEventName} - Evento customizado disparado
     */
    static addEventListenerDispatcher(
        fieldElementReference,
        listenerEventName,
        dispatchEventName,
        options = {
            dispatchOnlyIf: (e) => true,
            isUsingCardForm: false,
            onlyDispatch: false
        }
    ) {
        if (options.onlyDispatch) {
            document.dispatchEvent(new CustomEvent(`${MPCheckoutFieldsDispatcher.EVENT_NAME_PREFIX}${dispatchEventName}`));
            return;
        }

        if (options.isUsingCardForm) {
            fieldElementReference?.on(listenerEventName, (e) => {
                if (options.dispatchOnlyIf && !options.dispatchOnlyIf(e)) {
                    return;
                }

                document.dispatchEvent(new CustomEvent(`${MPCheckoutFieldsDispatcher.EVENT_NAME_PREFIX}${dispatchEventName}`));
            });

            return;
        }

        fieldElementReference?.addEventListener(listenerEventName, (e) => {
            if (options.dispatchOnlyIf && !options.dispatchOnlyIf(e)) {
                return;
            }

            document.dispatchEvent(new CustomEvent(`${MPCheckoutFieldsDispatcher.EVENT_NAME_PREFIX}${dispatchEventName}`));
        });
    }
}
