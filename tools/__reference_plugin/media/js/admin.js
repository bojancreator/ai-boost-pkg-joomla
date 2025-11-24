/**
 * @package   ShackOpenGraph
 * @author    Piotr Moćko
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2018 Perfect Web sp. z o.o., All rights reserved.
 * @copyright 2019-2024 Joomlashack. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of ShackOpenGraph.
 *
 * ShackOpenGraph is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ShackOpenGraph is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ShackOpenGraph.  If not, see <http://www.gnu.org/licenses/>.
 */
;jQuery(document).ready(function($) {
    var sitemap = {
        error : $('#jform_params_PLG_PWEBOPENGRAPH_SITEMAP_ERROR-lbl').parent(),
        label : $('#jform_params_PLG_PWEBOPENGRAPH_SITEMAP_URLS-lbl').parent(),
        input : $('#jform_params_sitemap_link'),
        urls  : $('<div>', {id: 'sitemap_urls'}),
        button: $('<input>', {
            id   : 'sitemap_button',
            type : 'button',
            name : 'scrape',
            class: 'btn',
            value: Joomla.JText._('PLG_PWEBOPENGRAPH_SITEMAP_BUTTON')
        })
    };

    sitemap.label.append(sitemap.urls).hide();
    sitemap.error.hide();

    sitemap.input.parent().append(sitemap.button);

    sitemap.button.on('click', function(evt) {
        var sitemapUrl = sitemap.input.val();

        sitemap.label.slideUp(function() {
            sitemap.urls.html('');
        });

        if (sitemapUrl) {
            $.ajax({
                url    : sitemapUrl,
                success: function(data, status, $xhr) {
                    sitemap.urls.append('<ul>');

                    $(data).find('loc').each(function(idx, el) {
                        try {
                            var url = el.firstChild.nodeValue;
                            sitemap.urls.append('<li>' + url + '</li>');

                            // send post request to Facebook scraper to refresh cache of link
                            $.post('https://graph.facebook.com', {id: url + '&scrape=true'});

                        } catch (err) {
                            // ignore
                        }
                    });
                    sitemap.urls.append('</ul>');
                    sitemap.label.slideDown();

                },
                error  : function($xhr, status, error) {
                    sitemap.error.slideDown(function() {
                        setTimeout(function() {
                            sitemap.error.slideUp();
                        }, 10000)
                    });
                }
            })
        }
    });
});
