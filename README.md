# UDA-V2

V2 目标：新架构重建，保留现有系统风格。

## 本地启动

1. 复制环境变量文件  
   - 将 `.env.example` 复制为 `.env`
2. 创建数据库  
   - 建议库名：`uda_v2`（与 `.env` 中 `DB_NAME` 一致；与 ECS 常见配置对齐）
3. 执行 SQL  
   - `database/migrations/001_init_core_tables.sql`
   - `database/migrations/003_add_user_contact_fields.sql`
   - `database/migrations/004_create_calendar_events.sql`
   - `database/migrations/005_add_user_locale.sql`
   - `database/seeders/001_permissions_seed.sql`
   - `database/seeders/002_bootstrap_admin.sql`
   - `database/seeders/003_permissions_system_pages_seed.sql`
   - `database/seeders/004_permissions_action_granular_seed.sql`
   - `database/seeders/005_permissions_calendar_seed.sql`
   - `database/seeders/006_permissions_calendar_menu_seed.sql`
4. 启动 PHP

```powershell
cd "C:\Users\jestu\Desktop\暂存\UDA内部管理网站\UDA-V2\public"
& "C:\xampp\php\php.exe" -S 127.0.0.1:5010 -t . index.php
```

访问：`http://127.0.0.1:5010/login`

### 一键启动（推荐）

在项目根目录双击：`start-v2.bat`  
会使用 XAMPP 的 PHP 启动本地服务（不自动跳转浏览器）。

### 一键全启动（MySQL + Web）

在项目根目录双击：`start-all.bat`  
会自动检查并启动 MySQL（若未运行），再启动 PHP 网站服务。

### 一键执行 SQL（推荐）

在项目根目录双击：`run-sql.bat`  
可用菜单选择常用 seeder（003/004）或自定义 SQL 文件。

## 第一阶段范围

- 权限中心（角色权限 + 用户单独权限）
- 主数据底座（客户/供应商/商品/仓库）
