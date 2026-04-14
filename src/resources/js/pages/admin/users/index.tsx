import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import SheetPanel from '@/components/sheet-panel';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    approved_at?: string;
    rejected_at?: string;
    rejection_reason?: string;
    created_at: string;
}

interface PageProps {
    users: User[];
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function UserManagement() {
    const { users, flash } = usePage<PageProps>().props;
    const [rejectingUser, setRejectingUser] = useState<User | null>(null);
    const [showRejected, setShowRejected] = useState(false);

    const approveForm = useForm({});
    const rejectForm = useForm({ reason: '' });
    const toggleAdminForm = useForm({});

    const handleApprove = (user: User) => {
        approveForm.post(route('admin.users.approve', user.id));
    };

    const handleReject = () => {
        if (!rejectingUser) return;

        rejectForm.post(route('admin.users.reject', rejectingUser.id), {
            onSuccess: () => {
                setRejectingUser(null);
                rejectForm.setData('reason', '');
            },
        });
    };

    const handleToggleAdmin = (user: User) => {
        toggleAdminForm.post(route('admin.users.toggle-admin', user.id));
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'secondary',
            approved: 'default',
            rejected: 'destructive',
        } as const;

        return <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>{status.charAt(0).toUpperCase() + status.slice(1)}</Badge>;
    };

    const filteredUsers = users.filter((user) => showRejected || user.approval_status !== 'rejected');

    return (
        <AdminLayout>
            <Head title="User Management" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold">User Management</h1>
                            <p className="text-muted-foreground">Manage user registrations and permissions</p>
                        </div>
                        <div className="flex items-center space-x-2">
                            <Checkbox id="show-rejected" checked={showRejected} onCheckedChange={(checked) => setShowRejected(checked === true)} />
                            <Label htmlFor="show-rejected" className="cursor-pointer">
                                Show rejected users
                            </Label>
                        </div>
                    </div>
                </div>

                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2 text-left">Name</th>
                                        <th className="p-2 text-left">Email</th>
                                        <th className="p-2 text-left">Status</th>
                                        <th className="p-2 text-left">Admin</th>
                                        <th className="p-2 text-left">Joined</th>
                                        <th className="p-2 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredUsers.map((user) => (
                                        <tr key={user.id} className="border-b">
                                            <td className="p-2">{user.name}</td>
                                            <td className="p-2">{user.email}</td>
                                            <td className="p-2">{getStatusBadge(user.approval_status)}</td>
                                            <td className="p-2">
                                                <Badge variant={user.is_admin ? 'default' : 'secondary'}>{user.is_admin ? 'Yes' : 'No'}</Badge>
                                            </td>
                                            <td className="p-2">{new Date(user.created_at).toLocaleDateString()}</td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                        {user.approval_status === 'pending' && (
                                                            <>
                                                                <Button size="sm" onClick={() => handleApprove(user)} disabled={approveForm.processing}>
                                                                    {approveForm.processing ? 'Approving...' : 'Approve'}
                                                                </Button>
                                                                <Button size="sm" variant="destructive" onClick={() => setRejectingUser(user)}>
                                                                    Reject
                                                                </Button>
                                                            </>
                                                        )}
                                                    {user.approval_status !== 'rejected' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleToggleAdmin(user)}
                                                            disabled={toggleAdminForm.processing}
                                                        >
                                                            {toggleAdminForm.processing
                                                                ? 'Updating...'
                                                                : user.is_admin
                                                                  ? 'Remove Admin'
                                                                  : 'Make Admin'}
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <SheetPanel
                open={!!rejectingUser}
                onOpenChange={(open) => { if (!open) { setRejectingUser(null); rejectForm.setData('reason', ''); rejectForm.clearErrors(); } }}
                title="Reject User"
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setRejectingUser(null);
                                rejectForm.setData('reason', '');
                                rejectForm.clearErrors();
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={!rejectForm.data.reason.trim() || rejectForm.processing}
                        >
                            {rejectForm.processing ? 'Rejecting...' : 'Reject'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-2">
                    <Label htmlFor="reason">Rejection Reason</Label>
                    <Textarea
                        id="reason"
                        value={rejectForm.data.reason}
                        onChange={(e) => rejectForm.setData('reason', e.target.value)}
                        placeholder="Enter reason for rejection..."
                    />
                    {rejectForm.errors.reason && (
                        <p className="text-sm text-red-500">
                            {rejectForm.errors.reason}
                        </p>
                    )}
                </div>
            </SheetPanel>
        </AdminLayout>
    );
}
