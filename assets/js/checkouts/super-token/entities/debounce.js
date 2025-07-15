/* eslint-disable no-unused-vars */
class MPDebounce {
    DEBOUNCE_TIME = 3000;

    inputDebounce(callback) {
        let inputTimeout;

        return (inputEvent) => {
            clearTimeout(inputTimeout);

            inputTimeout = setTimeout(() => callback(inputEvent), this.DEBOUNCE_TIME);
        };
    }
}