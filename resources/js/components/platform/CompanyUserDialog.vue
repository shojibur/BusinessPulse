<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/platform/companies/users';
import type {
    PlatformDepartmentOption,
    PlatformManagerOption,
    PlatformUserRow,
} from '@/types';

const props = defineProps<{
    companyId: number;
    departments: PlatformDepartmentOption[];
    managers: PlatformManagerOption[];
    /** The user being edited, or null to add a new one. */
    user: PlatformUserRow | null;
}>();

const open = defineModel<boolean>('open', { required: true });

// Ids are kept as strings so the selects can hold an empty value.
const form = useForm({
    name: '',
    email: '',
    job_title: '',
    role: 'staff',
    department_id: '',
    manager_id: '',
    password: '',
});

const editing = computed(() => props.user !== null);
const needsDepartment = computed(() => form.role !== 'owner');
const needsManager = computed(() => form.role === 'staff');

// A rule message that is not tied to one field (for example: a company must keep an active Owner).
const generalError = computed(
    () => (form.errors as Record<string, string | undefined>).user,
);

// Managers of the chosen department only.
const managerOptions = computed(() =>
    props.managers.filter(
        (manager) => String(manager.department_id) === form.department_id,
    ),
);

// Load the form each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.name = props.user?.name ?? '';
    form.email = props.user?.email ?? '';
    form.job_title = props.user?.job_title ?? '';
    form.role = props.user?.role ?? 'staff';
    form.department_id =
        props.user?.department_id !== null && props.user !== null
            ? String(props.user.department_id)
            : String(props.departments[0]?.id ?? '');
    form.manager_id =
        props.user?.manager_id !== null && props.user !== null
            ? String(props.user.manager_id)
            : '';
    form.password = '';
});

// A Staff user's Manager must belong to the chosen department.
watch(
    () => form.department_id,
    () => {
        if (
            !managerOptions.value.some((m) => String(m.id) === form.manager_id)
        ) {
            form.manager_id = '';
        }
    },
);

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => form.reset('password'),
    };

    form.transform((data) => ({
        ...data,
        department_id: needsDepartment.value ? data.department_id : null,
        manager_id: needsManager.value ? data.manager_id : null,
        // An edit never sends a password; use Reset password instead.
        password: editing.value ? undefined : data.password,
    }));

    if (props.user === null) {
        form.post(store({ company: props.companyId }).url, options);
    } else {
        form.put(
            update({ company: props.companyId, user: props.user.id }).url,
            options,
        );
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form novalidate class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit user' : 'Add user'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? 'Change the profile, role or place in the company. A role or email change signs the user out.'
                                : 'The user signs in with this email and password.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="user-name">Full name</Label>
                    <Input id="user-name" v-model="form.name" />
                    <p
                        v-if="form.errors.name"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="user-email">Email address</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                    />
                    <p
                        v-if="form.errors.email"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="user-title">Job title (optional)</Label>
                    <Input id="user-title" v-model="form.job_title" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="user-role">Role</Label>
                        <NativeSelect id="user-role" v-model="form.role">
                            <option value="owner">Owner</option>
                            <option value="manager">Manager</option>
                            <option value="staff">Staff</option>
                        </NativeSelect>
                        <p
                            v-if="form.errors.role"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ form.errors.role }}
                        </p>
                    </div>
                    <div v-if="needsDepartment" class="grid gap-2">
                        <Label for="user-department">Department</Label>
                        <NativeSelect
                            id="user-department"
                            v-model="form.department_id"
                        >
                            <option
                                v-for="department in departments"
                                :key="department.id"
                                :value="String(department.id)"
                            >
                                {{ department.name }}
                            </option>
                        </NativeSelect>
                        <p
                            v-if="form.errors.department_id"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ form.errors.department_id }}
                        </p>
                    </div>
                </div>
                <div v-if="needsManager" class="grid gap-2">
                    <Label for="user-manager">Reports to (Manager)</Label>
                    <NativeSelect id="user-manager" v-model="form.manager_id">
                        <option value="">Choose a Manager</option>
                        <option
                            v-for="manager in managerOptions"
                            :key="manager.id"
                            :value="String(manager.id)"
                        >
                            {{ manager.name }}
                        </option>
                    </NativeSelect>
                    <p
                        v-if="managerOptions.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        This department has no active Manager yet. Add a Manager
                        first.
                    </p>
                    <p
                        v-if="form.errors.manager_id"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.manager_id }}
                    </p>
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="user-password"
                        >Password (at least 12 characters)</Label
                    >
                    <Input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                    />
                    <p
                        v-if="form.errors.password"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>
                <p
                    v-if="generalError"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ generalError }}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        {{ editing ? 'Save changes' : 'Add user' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
