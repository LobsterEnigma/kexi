# 架构与边界

## 数据所有权

```text
User
  -> Timetable
       -> Course
            -> CourseMeeting
       -> Share
       -> AcademicTask (optional Course link)
            -> AcademicTaskEntry (milestone / study)
```

课程只属于一张课表。所有私有读写都通过 Laravel Policy 检查课表所有者；管理员不会因为角色而自动获得私人课表读取权限。管理端只治理账户和分享状态。

## 教学周规则

`CourseMeeting` 是事实数据：星期、起止时间、地点和周次规则。`all`、`odd`、`even` 使用包含两端的周次范围；`specific` 保存去重排序后的指定周列表。跨午夜时间段在首版不支持，结束时间必须晚于开始时间。

教学周固定为周一至周日，开学日期所在周为第 1 周；`Timetable::weekStartDate()` 与 `occurrenceDate()` 统一计算日期。开学前和结课后不产生课程，月历周号、日期跳转、请假记录与导出使用同一规则。保存实际结课日期时，总周数包含首尾不完整周。

## 学业安排

`AcademicTask` 归属于课表；关联课程必须属于同一课表，删除课程只解除关联，删除任务会级联删除其子安排。子安排更新再次核对任务归属，普通用户、管理员均不能绕过课表所有权。

开放窗口由 `opens_at` / `due_at` 表达；实际考试由 `starts_at` / `ends_at` 表达。`AcademicTaskEntry` 区分 Project 阶段和个人学习时段，支持独立完成与提醒。学习与考试可跨午夜，单次最长 7 天；按课表时区输入与展示，数据库以 UTC 保存。

`AcademicPlanner` 将截止和开放窗口放在日期栏，实际时段按当地日期切片，与课程共同分配重叠列。学业时段的重叠提示独立于原有课程间隔诊断。整个任务完成后，所有子安排日历显示淡化且不再提醒；重新打开不会覆盖子安排自身完成状态。

站内提醒在请求时计算，浏览器每 60 秒刷新；达到配置的提前时间后出现，直到确认已读或完成。更改截止/开始/提醒时间会重置相应已读状态。没有后台邮件、推送或调度任务依赖。任务数据与提醒均不进入公开分享和课程图片。

## 课程冲突与间隔

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
