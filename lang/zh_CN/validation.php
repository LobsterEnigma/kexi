<?php

return [
    'accepted' => ':attribute 必须接受。',
    'after' => ':attribute 必须晚于 :date。',
    'before_or_equal' => ':attribute 不能晚于 :date。',
    'between' => [
        'numeric' => ':attribute 必须在 :min 到 :max 之间。',
    ],
    'confirmed' => ':attribute 两次输入不一致。',
    'date' => ':attribute 必须是有效日期。',
    'date_format' => ':attribute 格式应为 :format。',
    'email' => ':attribute 必须是有效邮箱。',
    'integer' => ':attribute 必须是整数。',
    'max' => [
        'array' => ':attribute 最多包含 :max 项。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'min' => [
        'array' => ':attribute 至少包含 :min 项。',
        'string' => ':attribute 不能少于 :min 个字符。',
    ],
    'required' => ':attribute 不能为空。',
    'string' => ':attribute 必须是文本。',
    'unique' => '该 :attribute 已被使用。',
    'attributes' => [
        'name' => '名称',
        'email' => '邮箱',
        'password' => '密码',
        'password_confirmation' => '确认密码',
        'expires_at' => '过期时间',
    ],
];
