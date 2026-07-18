<section>
    <header>
        <h2 class="text-lg font-bold leading-7 text-red-800">删除账户</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">此操作会永久删除所有课表、课程和分享记录，且无法恢复。</p>
    </header>

    <button class="wb-btn wb-btn--danger mt-6" type="button" x-data x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        <i data-lucide="trash-2"></i>删除我的账户
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form class="p-6 sm:p-7" method="POST" action="{{ route('profile.destroy') }}">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-bold leading-7 text-slate-900">确认永久删除账户？</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">请输入当前密码。提交后，全部账户数据会立即删除。</p>

            <label class="mt-5 block">
                <span class="wb-label">当前密码</span>
                <input class="wb-field" type="password" name="password" autocomplete="current-password" placeholder="输入密码确认">
                @foreach ($errors->userDeletion->get('password') as $message)<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@endforeach
            </label>

            <div class="mt-6 flex justify-end gap-2">
                <button class="wb-btn" type="button" x-on:click="$dispatch('close')">取消</button>
                <button class="wb-btn wb-btn--danger" type="submit">永久删除</button>
            </div>
        </form>
    </x-modal>
</section>
