import { Form, usePage } from "@inertiajs/react";
import { Eye, EyeOff, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type PageProps = {
    flash?: {
        loginRetryAfter?: number | null;
    };
};

export function LoginForm() {
    const { flash } = usePage<PageProps>().props;

    const [showPassword, setShowPassword] = useState(false);
    const [retryAfter, setRetryAfter] = useState(
        flash?.loginRetryAfter ?? 0,
    );

    useEffect(() => {
        setRetryAfter(flash?.loginRetryAfter ?? 0);
    }, [flash?.loginRetryAfter]);

    useEffect(() => {
        if (retryAfter <= 0) {
            return;
        }

        const timer = window.setInterval(() => {
            setRetryAfter((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [retryAfter]);

    const rateLimited = retryAfter > 0;

    return (
        <div className="w-full max-w-sm">
            <div className="mb-8 text-center lg:text-left">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Welcome back
                </h1>

                <p className="mt-2 text-sm text-muted-foreground">
                    Sign in to your accounting system.
                </p>
            </div>

            <Form
                action="/login"
                method="post"
                resetOnSuccess={["password"]}
                className="space-y-5"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>

                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                autoFocus
                                placeholder="you@example.com"
                                aria-invalid={!!errors.email}
                                disabled={processing || rateLimited}
                            />

                            {errors.email && !rateLimited && (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>

                            <div className="relative">
                                <Input
                                    id="password"
                                    name="password"
                                    type={showPassword ? "text" : "password"}
                                    autoComplete="current-password"
                                    placeholder="Enter your password"
                                    className="pr-10"
                                    aria-invalid={!!errors.password}
                                    disabled={processing || rateLimited}
                                />

                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword((value) => !value)
                                    }
                                    disabled={rateLimited}
                                    className="absolute right-0 top-0 flex h-9 w-9 items-center justify-center text-muted-foreground hover:text-foreground"
                                    aria-label={
                                        showPassword
                                            ? "Hide password"
                                            : "Show password"
                                    }
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <EyeOff className="size-4" />
                                    ) : (
                                        <Eye className="size-4" />
                                    )}
                                </button>
                            </div>

                            {errors.password && !rateLimited && (
                                <p className="text-sm text-destructive">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                name="remember"
                                type="checkbox"
                                value="1"
                                className="size-4 rounded border-input accent-primary"
                                disabled={processing || rateLimited}
                            />
                            Remember me
                        </label>

                        {/* {rateLimited && (
                            <p className="text-center text-sm text-destructive">
                                Too many login attempts. Please try again in{" "}
                                <span className="font-semibold tabular-nums">
                                    {retryAfter}s
                                </span>
                                .
                            </p>
                        )} */}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing || rateLimited}
                        >
                            {processing && (
                                <LoaderCircle className="size-4 animate-spin" />
                            )}

                            {rateLimited
                                ? `Too many login attempts. Please try again in ${retryAfter}s`
                                : processing
                                  ? "Signing in..."
                                  : "Sign in"}
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-8 text-center text-xs text-muted-foreground">
                Accounting System
            </p>
        </div>
    );
}