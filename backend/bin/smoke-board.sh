#!/bin/sh
# Smoke-тест API доски: направление, инициатива, проект, правило смены колонки.
# Запуск с хоста: sh backend/bin/smoke-board.sh [http://localhost:8080]
set -e

API_URL=${1:-http://localhost:8080}
EMAIL="smoke-$(date +%s)@jwp-board.local"
JSON='Content-Type: application/json'
ACCEPT='Accept: application/json'

echo "1. Регистрация"
TOKEN=$(curl -s -X POST "$API_URL/api/v1/user/register" -H "$JSON" -H "$ACCEPT" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"secret-password\",\"password_confirmation\":\"secret-password\"}" \
    | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
    echo "   ОШИБКА: токен не получен"
    exit 1
fi

AUTH="Authorization: Bearer $TOKEN"
echo "   ок"

echo "2. Направление"
DIRECTION=$(curl -s -X POST "$API_URL/api/v1/directions" -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
    -d '{"title":"Здоровье","color":"c3"}')
echo "   $DIRECTION"
DIRECTION_ID=$(echo "$DIRECTION" | sed -n 's/.*"id":\([0-9]*\).*/\1/p')

echo "3. Инициатива"
INITIATIVE=$(curl -s -X POST "$API_URL/api/v1/tasks" -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
    -d "{\"type\":\"initiative\",\"column\":\"in_progress\",\"title\":\"Спорт\",\"direction_id\":$DIRECTION_ID}")
echo "   $INITIATIVE"
INITIATIVE_ID=$(echo "$INITIATIVE" | sed -n 's/.*"id":\([0-9]*\).*/\1/p')

echo "4. Проект под инициативой"
PROJECT=$(curl -s -X POST "$API_URL/api/v1/tasks" -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
    -d "{\"type\":\"project\",\"column\":\"in_progress\",\"title\":\"Тренажерка\",\"initiative_id\":$INITIATIVE_ID}")
echo "   $PROJECT"
PROJECT_ID=$(echo "$PROJECT" | sed -n 's/.*"id":\([0-9]*\).*/\1/p')

echo "5. Смена колонки инициативы при проекте в работе (ожидается 409)"
curl -s -o /dev/null -w '   код: %{http_code}\n' -X PATCH "$API_URL/api/v1/tasks/$INITIATIVE_ID" \
    -H "$AUTH" -H "$JSON" -H "$ACCEPT" -d '{"column":"complete"}'

echo "6. Проект в готово, затем инициатива (ожидается 200 и 200)"
curl -s -o /dev/null -w '   проект: %{http_code}\n' -X PATCH "$API_URL/api/v1/tasks/$PROJECT_ID" \
    -H "$AUTH" -H "$JSON" -H "$ACCEPT" -d '{"column":"complete"}'
curl -s -o /dev/null -w '   инициатива: %{http_code}\n' -X PATCH "$API_URL/api/v1/tasks/$INITIATIVE_ID" \
    -H "$AUTH" -H "$JSON" -H "$ACCEPT" -d '{"column":"complete"}'

echo "7. Список карточек"
curl -s "$API_URL/api/v1/tasks" -H "$AUTH" -H "$ACCEPT"
echo
