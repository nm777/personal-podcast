import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { useColorScheme, type ColorScheme } from '@/hooks/use-color-scheme';
import { useInitials } from '@/hooks/use-initials';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Moon, Palette, Settings, Sun, User, Users } from 'lucide-react';

export default function AppTopbar() {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const { auth } = usePage<any>().props;
    const getInitials = useInitials();
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();
    const { colorScheme, updateColorScheme } = useColorScheme();

    const isDark =
        appearance === 'dark' ||
        (appearance === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    const toggleTheme = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    const schemeOptions: { value: ColorScheme; label: string; color: string }[] = [
        { value: 'default', label: 'Default', color: 'bg-neutral-400' },
        { value: 'ocean', label: 'Ocean', color: 'bg-blue-500' },
        { value: 'forest', label: 'Forest', color: 'bg-green-600' },
        { value: 'ember', label: 'Ember', color: 'bg-orange-500' },
    ];

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <header className="sticky top-0 z-50 flex h-14 items-center justify-between border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <Link href={route('dashboard')} className="text-lg font-semibold tracking-tight">
                PodKeep
            </Link>

            <div className="flex items-center gap-2">
                <Button variant="ghost" size="icon" className="h-9 w-9" onClick={toggleTheme}>
                    {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                    <span className="sr-only">Toggle theme</span>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-9 w-9">
                            <Palette className="h-4 w-4" />
                            <span className="sr-only">Color scheme</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {schemeOptions.map((option) => (
                            <DropdownMenuItem key={option.value} onClick={() => updateColorScheme(option.value)} className="flex items-center gap-2">
                                <span className={`inline-block h-3 w-3 rounded-full ${option.color}`} />
                                {option.label}
                                {colorScheme === option.value && <span className="ml-auto text-xs">✓</span>}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="relative h-9 w-9 rounded-full">
                            <Avatar className="h-8 w-8">
                                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                <AvatarFallback className="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56" align="end" forceMount>
                        <DropdownMenuLabel className="font-normal">
                            <div className="flex flex-col space-y-1">
                                <p className="text-sm leading-none font-medium">{auth.user.name}</p>
                                <p className="text-xs leading-none text-muted-foreground">{auth.user.email}</p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link className="block w-full cursor-pointer" href={route('profile.edit')} as="button" prefetch onClick={cleanup}>
                                    <User className="mr-2 h-4 w-4" />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link className="block w-full cursor-pointer" href={route('password.edit')} as="button" prefetch onClick={cleanup}>
                                    <Settings className="mr-2 h-4 w-4" />
                                    Password
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                        {auth.user.is_admin && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem asChild>
                                        <Link className="block w-full cursor-pointer" href="/admin/users" as="button" onClick={cleanup}>
                                            <Users className="mr-2 h-4 w-4" />
                                            User Management
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </>
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link className="block w-full cursor-pointer" method="post" href={route('logout')} as="button" onClick={handleLogout}>
                                <LogOut className="mr-2 h-4 w-4" />
                                Log out
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
