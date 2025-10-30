# 🤖 Telegram Бот для Системотехников СВГТК

Telegram бот для получения и обработки заявок системотехниками.

## 🎯 Возможности

- ✅ **Уведомления о новых заявках** - мгновенные push-уведомления
- ✅ **Просмотр заявок** - новые, мои, все активные
- ✅ **Управление заявками** - взять в работу, завершить
- ✅ **Статистика** - количество выполненных заявок
- ✅ **Inline кнопки** - удобное управление прямо из Telegram

## 📱 Команды

```
/start логин     - Привязать аккаунт системотехника
/new             - Показать новые заявки
/my              - Показать мои заявки (в работе)
/all             - Показать все активные заявки
/stats           - Показать мою статистику
/help            - Справка
```

## 🚀 Быстрый старт

### 1. Установка

```bash
# Скопируйте папку bot в корень проекта
C:\OSPanel\domains\project\bot\

# Выполните миграцию БД
mysql -u root -p svgtk_requests < bot/migration_telegram.sql
```

### 2. Запуск (локально)

```bash
cd C:\OSPanel\domains\project\bot
php polling.php
```

### 3. Использование

1. Откройте [@svgtk_zayavki_bot](https://t.me/svgtk_zayavki_bot)
2. Отправьте `/start ВАШ_ЛОГИН`
3. Используйте команды для работы с заявками

## 📖 Документация

- [INSTALLATION.md](INSTALLATION.md) - Подробная инструкция по установке
- [config.php](config.php) - Конфигурация бота

## 🏗️ Структура

```
bot/
├── config.php              # Конфигурация
├── webhook.php             # Webhook обработчик
├── polling.php             # Long polling (для разработки)
├── set_webhook.php         # Установка webhook
├── migration_telegram.sql  # SQL миграция
├── INSTALLATION.md         # Инструкция
│
├── commands/               # Команды бота
│   ├── start.php           # /start - привязка аккаунта
│   ├── new.php             # /new - новые заявки
│   ├── my.php              # /my - мои заявки
│   ├── all.php             # /all - все заявки
│   ├── stats.php           # /stats - статистика
│   └── help.php            # /help - помощь
│
├── helpers/                # Вспомогательные функции
│   ├── telegram.php        # Работа с Telegram API
│   └── database.php        # Работа с БД
│
└── notifications/          # Уведомления
    └── send.php            # Отправка уведомлений
```

## 🔧 Интеграция с сайтом

### Отправка уведомлений при создании заявки

```php
// В файле создания заявки (после INSERT)
require_once __DIR__ . '/bot/notifications/send.php';
notifyNewRequest($requestId);
```

## 📊 База данных

Добавлены поля в таблицу `users`:

```sql
telegram_id              BIGINT        - Telegram ID пользователя
telegram_username        VARCHAR(100)  - Telegram username
telegram_notifications   BOOLEAN       - Включены ли уведомления
```

## 🔐 Безопасность

- ✅ Только системотехники могут привязать аккаунт
- ✅ Один Telegram = один аккаунт
- ✅ Техники видят только свои и свободные заявки
- ✅ Логирование всех действий

## 🐛 Troubleshooting

### Бот не отвечает
- Проверьте что `polling.php` запущен
- Посмотрите логи: `bot/bot.log`

### Не приходят уведомления
- Проверьте `telegram_notifications = 1`
- Добавьте код `notifyNewRequest()` в создание заявок

### Кнопки не работают
- Убедитесь что `polling.php` запущен
- Проверьте статус заявки (approved/in_progress)

## 📝 Логи

Все действия логируются в `bot/bot.log`:

```
[2025-10-29 14:30:45] Webhook received | {...}
[2025-10-29 14:30:45] Processing message | {...}
[2025-10-29 14:30:46] Account linked successfully | {...}
```

## 🎉 Готово!

Бот полностью настроен и готов к работе!

**Bot:** [@svgtk_zayavki_bot](https://t.me/svgtk_zayavki_bot)  
**Version:** 1.0  
**Date:** 2025-10-29
