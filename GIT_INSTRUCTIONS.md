# 🔄 Инструкция по синхронизации с Git

## 🚀 Быстрый старт

### Если репозиторий ещё НЕ создан:

```bash
# 1. Инициализируйте Git в папке проекта
cd /path/to/svgtk-requests
git init

# 2. Скопируйте файлы из git-package
cp git-package/.gitignore .
cp git-package/README.md .
cp git-package/CHANGELOG.md .
cp git-package/admin_logs.php .
cp git-package/admin_dashboard.php .
cp git-package/logout.php .
cp git-package/config/ldap.php config/
cp git-package/includes/auth.php includes/

# 3. Создайте config/db.php (НЕ коммитьте его!)
cp config/db.php.example config/db.php
# Отредактируйте config/db.php с вашими данными БД

# 4. Добавьте файлы в Git
git add .
git commit -m "feat: LDAP integration with full logging

- Add LDAP/AD authentication for shc.local domain
- Add hybrid auth (LDAP + local DB)
- Add full action logging (login, logout, failed attempts)
- Fix logout logging issue
- Fix table name (login_logs → user_logs)
- Fix domain name case (Shc → shc)
- Add auth_type field to users and logs
- Add local admin fallback

BREAKING CHANGE: Requires database migration"

# 5. Создайте репозиторий на GitHub/GitLab

# 6. Подключите remote и push
git remote add origin https://github.com/your-username/svgtk-requests.git
git branch -M main
git push -u origin main
```

---

### Если репозиторий УЖЕ существует:

```bash
# 1. Перейдите в папку проекта
cd /path/to/svgtk-requests

# 2. Создайте новую ветку для изменений
git checkout -b feature/ldap-integration

# 3. Скопируйте обновлённые файлы
cp git-package/admin_logs.php .
cp git-package/admin_dashboard.php .
cp git-package/logout.php .
cp git-package/config/ldap.php config/
cp git-package/includes/auth.php includes/
cp git-package/CHANGELOG.md .
cp git-package/README.md .

# 4. Проверьте изменения
git status
git diff

# 5. Добавьте изменения
git add admin_logs.php admin_dashboard.php logout.php
git add config/ldap.php includes/auth.php
git add CHANGELOG.md README.md

# 6. Коммит с подробным описанием
git commit -m "feat: LDAP integration with full logging

Features:
- LDAP/AD authentication for shc.local domain
- Hybrid authentication (LDAP + local DB fallback)
- Full action logging (login, logout, failed attempts)
- Distinguish auth type in logs (LDAP vs Local)
- Auto-create users from domain
- Local admin fallback (local_admin)

Fixes:
- Fix logout logging (pdo injection)
- Fix table name (login_logs → user_logs) 
- Fix domain case (dc=Shc → dc=shc)
- Fix auth type tracking in logs

Database changes:
- Add auth_type to users table
- Add auth_type, success, error_message to logs table
- Requires migration files execution

Files changed:
- admin_logs.php: Fix table name
- admin_dashboard.php: Fix table name  
- logout.php: Fix logging with proper pdo injection
- config/ldap.php: Fix domain case, add shc.local config
- includes/auth.php: Add LDAP auth, fix logout logging
- migrations/: Add migration scripts

BREAKING CHANGE: Requires database migration before deployment"

# 7. Push в удалённый репозиторий
git push origin feature/ldap-integration

# 8. Создайте Pull Request на GitHub/GitLab
```

---

## 📝 Стиль коммитов (Conventional Commits)

Используем формат: `<тип>(<область>): <описание>`

### Типы:
- `feat:` - новая функциональность
- `fix:` - исправление бага
- `docs:` - изменения в документации
- `style:` - форматирование кода
- `refactor:` - рефакторинг
- `test:` - добавление тестов
- `chore:` - обновление зависимостей, конфигурации

### Примеры хороших коммитов:

```bash
git commit -m "feat(auth): add LDAP authentication support"
git commit -m "fix(logs): fix logout action not being logged"
git commit -m "fix(ldap): correct domain case shc.local"
git commit -m "docs: update README with LDAP setup instructions"
git commit -m "refactor(auth): improve logout function with pdo injection"
```

---

## 🔐 Что НЕ коммитить

Файлы в `.gitignore` НЕ попадут в Git:

- ❌ `config/db.php` - содержит пароли БД
- ❌ `*.log` - логи
- ❌ `.env` - переменные окружения
- ❌ `vendor/` - зависимости
- ❌ `uploads/` - загрузки пользователей

---

## 📊 Миграции базы данных

### Создайте папку migrations:

```bash
mkdir migrations
cp git-package/../migration_add_ldap_support.sql migrations/
cp git-package/../migration_add_auth_type_to_logs.sql migrations/
git add migrations/
git commit -m "chore: add database migration scripts"
```

---

## 🏷️ Теги версий

После мержа в main:

```bash
git checkout main
git pull origin main
git tag -a v2.0.0 -m "Version 2.0.0: LDAP Integration

Major changes:
- LDAP/AD authentication
- Full action logging  
- Hybrid auth system
- Logout logging fix"

git push origin v2.0.0
```

---

## 🌿 Workflow веток

```
main (production)
  ↓
develop (staging)
  ↓
feature/ldap-integration
feature/new-feature
fix/logout-logging
```

### Рекомендуемый flow:

1. Создаёте feature ветку от `develop`
2. Разрабатываете, коммитите
3. Push и создаёте Pull Request в `develop`
4. После ревью и тестов - мерж в `develop`
5. Периодически мержите `develop` → `main`

---

## 🔍 Полезные команды

```bash
# Посмотреть историю
git log --oneline --graph --all

# Посмотреть изменения в файле
git log -p admin_logs.php

# Кто изменял строку
git blame admin_logs.php

# Откатить файл к предыдущей версии
git checkout HEAD~1 admin_logs.php

# Посмотреть изменения перед коммитом
git diff --staged
```

---

## ✅ Чеклист перед Push

- [ ] Код работает локально
- [ ] Миграции БД выполнены
- [ ] Конфиденциальные данные не в коммите
- [ ] README.md обновлён
- [ ] CHANGELOG.md обновлён
- [ ] Коммит message описывает изменения
- [ ] Тесты пройдены (если есть)

---

## 📞 Помощь

Если что-то пошло не так:

```bash
# Отменить последний коммит (но оставить изменения)
git reset --soft HEAD~1

# Отменить изменения в файле
git checkout -- filename

# Посмотреть что коммитится
git status
git diff --staged
```

---

**Важно:** Всегда делайте `git pull` перед `git push`!
