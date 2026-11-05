#!/bin/sh
# Проверка синтаксиса PHP по всему приложению (запускается внутри контейнера php).
# Используется как быстрый фильтр до прогона PHPStan/PHPUnit.
find app config database routes tests bootstrap -name '*.php' -print0 \
    | xargs -0 -n1 php -l \
    | grep -v 'No syntax errors detected' || true
echo '--- syntax check done'
