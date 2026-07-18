# 架构与边界

## 数据所有权

```text
User
  -> Timetable
       -> Course
            -> CourseMeeting
       -> Share
```

课程只属于一张课表。所有私有读写都通过 Laravel Policy 检查课表所有者；管理员不会因为角色而自动获得私人课表读取权限。管理端只治理账户和分享状态。

## 教学周规则

`CourseMeeting` 是事实数据：星期、起止时间、地点和周次规则。`all`、`odd`、`even` 使用包含两端的周次范围；`specific` 保存去重排序后的指定周列表。跨午夜时间段在首版不支持，结束时间必须晚于开始时间。

`ScheduleAnalyzer` 在请求时为当前教学周生成 occurrence，按星期分组后两两比较：

```text
overlap = min(endA, endB) - max(startA, startB)
overlap > 0        -> conflict
overlap <= 0,
gap <= threshold   -> near
otherwise          -> slack light / medium / deep
```

绿色档位相对于临近阈值 `T` 计算：`T < gap <= 2T`、`2T < gap <= 4T`、`gap > 4T`。同日没有其他课程时使用最宽松档。

## 分享安全

- 明文 token 只在创建时显示一次，数据库只保存 SHA-256 摘要。
- 可选访问密码使用 Laravel 哈希，不保存明文。
- 全局开关、用户分享状态、管理员暂停、撤销、过期和账户封禁会在每次访问时统一检查。
- 密码解锁只保存在服务器会话中，并绑定 `access_version`；撤销会使已有解锁立即失效。

## 封禁与会话

封禁用户会增加 `auth_version`、旋转 remember token、删除数据库会话并撤销活动分享。每个认证请求都会比较会话版本，因此旧设备不会继续保留访问权。登录失败信息不区分密码错误和账户封禁。

## 管理权限

管理端使用独立中间件和显式控制器，不使用全局 `Gate::before`。封禁、分享治理和站点开关写入 `admin_audit_logs`，审计内容不会保存密码或分享 token。
