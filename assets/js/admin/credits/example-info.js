/* globals mp_info_admin_credits_script_params */

(function () {
    let blueBadgeArr = document.querySelectorAll('.credits-info-example-buttons-child')
    let iconImg = document.querySelectorAll('.icon-image')
    let gifImg = document.querySelector('#gif-image')
    let exampleFooter = document.querySelector('#credits-info-example-gif-footer')

    let btnFirst = document.querySelector('#btn-first')
    let btnSecond = document.querySelector('#btn-second')

    if (blueBadgeArr.length > 0) {
        verify()

        function verify() {

            if (blueBadgeArr[0].classList.contains('selected')) {
                btnFirst.classList.add('credits-info-blue-color')
                btnSecond.classList.remove('credits-info-blue-color')
                iconImg[0].setAttribute('src', mp_info_admin_credits_script_params.computerBlueIcon)
                iconImg[1].setAttribute('src', mp_info_admin_credits_script_params.cellphoneGrayIcon)
                gifImg.setAttribute('src', mp_info_admin_credits_script_params.viewDesktop)
                exampleFooter.innerHTML = mp_info_admin_credits_script_params.footerDesktop
                return
            }
            if (blueBadgeArr[1].classList.contains('selected')) {
                btnSecond.classList.add('credits-info-blue-color')
                btnFirst.classList.remove('credits-info-blue-color')
                iconImg[1].setAttribute('src', mp_info_admin_credits_script_params.cellphoneBlueIcon)
                iconImg[0].setAttribute('src', mp_info_admin_credits_script_params.computerGrayIcon)
                gifImg.setAttribute('src', mp_info_admin_credits_script_params.viewMobile)
                exampleFooter.innerText = mp_info_admin_credits_script_params.footerCellphone
                return
            }
        }



        blueBadgeArr[0].addEventListener('click', () => {
            if (blueBadgeArr[0].classList.contains('selected')) {
                return
            } else {
                blueBadgeArr[0].classList.add('selected')
                blueBadgeArr[1].classList.remove('selected')

            }
            verify()
        })

        blueBadgeArr[1].addEventListener('click', () => {
            if (blueBadgeArr[1].classList.contains('selected')) {
                return
            } else {
                blueBadgeArr[1].classList.add('selected')
                blueBadgeArr[0].classList.remove('selected')
            }
            verify()
        })
    }
})()
