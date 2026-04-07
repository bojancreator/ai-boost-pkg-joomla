/**
 * @package   admintools
 * @copyright Copyright (c)2010-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

((window, document) =>
{
    var interval = null;

    const armButtons = () => {
        const button1 = document.getElementById("plgSystemAdmintoolsDBE");
        const button2 = document.getElementById("plgSystemAdmintoolsΗΒΕΜ");

        if (!button1 || !button2)
        {
            return;
        }

        clearInterval(interval);
        interval = null;

        button1?.classList.remove('d-none')
        button2?.classList.remove('d-none')

        button1?.addEventListener("click", (e) =>
        {
            console.log('skaghghkasf');

            const url = Joomla.getOptions('plg_system_admintools')?.blockedRequestsEmailReminder?.dbeURL;

            if (!url)
            {
                return;
            }

            window.location = url;
        })

        button2?.addEventListener("click", (e) =>
        {
            const url = Joomla.getOptions('plg_system_admintools')?.blockedRequestsEmailReminder?.hbemURL;

            if (!url)
            {
                return;
            }

            window.location = url;
        })
    }

    interval = setInterval(armButtons, 100)
})(window, document);