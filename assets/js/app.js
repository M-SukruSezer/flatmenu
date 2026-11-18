/* Besin değerleri satırının açılış hareketi.
 *
 * Menü kartının katlanmış bir iç sayfası gibi açılır: yükseklik yukarıdan
 * aşağı çözülür, metin hemen ardından gelir. Kapanış açılışın yaklaşık
 * %65'i kadar sürer ve hızlanan eğriyle biter.
 *
 * İlerlemeli geliştirme: bu dosya hiç çalışmazsa <details> kendi doğal
 * davranışıyla anında açılıp kapanır, hiçbir bilgi kaybolmaz.
 * "Hareketi azalt" tercihi açıksa da doğal davranışa bırakılır.
 */
(function () {
    'use strict';

    if (!document.querySelectorAll || !Element.prototype.animate) return;

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;

    var OPEN_MS  = 260;
    var CLOSE_MS = 170;                              // açılışın ~%65'i
    var EASE_IN  = 'cubic-bezier(.2,.7,.3,1)';       // yavaşlayarak yerine oturur
    var EASE_OUT = 'cubic-bezier(.5,0,.75,.4)';      // hızlanarak kapanır

    Array.prototype.forEach.call(
        document.querySelectorAll('details.facts-toggle'),
        function (details) {
            var summary = details.querySelector('summary');
            var body = details.querySelector('.facts-body');
            if (!summary || !body) return;

            var running = null;

            // Niyet edilen durum ayrı tutulur. Kapanma animasyonu sürerken
            // details.open hâlâ true olduğundan, ona bakarak karar vermek
            // hızlı tıklamada "tekrar kapat" demek olurdu.
            var isOpen = details.open;

            summary.addEventListener('click', function (event) {
                event.preventDefault();

                // Yarıda kesilirse bulunduğu yükseklikten devam et,
                // başa dönüp yeniden oynatma.
                var from = body.getBoundingClientRect().height;
                if (running) {
                    running.cancel();
                    running = null;
                }

                isOpen = !isOpen;
                var opening = isOpen;
                if (opening) details.open = true;   // ölçmeden önce render et

                var to = opening ? body.scrollHeight : 0;

                var anim = body.animate(
                    [
                        { height: from + 'px', opacity: opening ? 0 : 1 },
                        { height: to + 'px',   opacity: opening ? 1 : 0 }
                    ],
                    {
                        duration: opening ? OPEN_MS : CLOSE_MS,
                        easing: opening ? EASE_IN : EASE_OUT
                    }
                );
                running = anim;

                // Kimlik denetimi şart: iptal edilen bir animasyonun oncancel'ı
                // sonradan tetiklenip yerine geçen animasyonun referansını
                // silerse, bir sonraki tıklama onu iptal edemiyor ve geride
                // kalan kapanma animasyonu bitince durumu geri çeviriyordu.
                anim.onfinish = function () {
                    if (running !== anim) return;
                    running = null;
                    if (!opening) details.open = false;
                };
                anim.oncancel = function () {
                    if (running === anim) running = null;
                };
            });
        }
    );
}());
