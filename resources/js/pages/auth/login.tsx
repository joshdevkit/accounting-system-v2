import { Head } from "@inertiajs/react";
import { Calculator } from "lucide-react";

import { LoginForm } from "@/components/auth/login-form";

type LoginProps = {
    companyName: string;
};

export default function Login({ companyName }: LoginProps) {
    return (
        <>
            <Head title="Sign in" />

            <main className="min-h-svh bg-background lg:grid lg:grid-cols-2">
                <section className="flex min-h-svh items-center justify-center px-6 py-12">
                    <LoginForm />
                </section>

                <section className="relative hidden overflow-hidden lg:block">
                    <div
                        className="absolute inset-0 bg-cover bg-center"
                        style={{
                            backgroundImage: "url('/login-back.jpg')",
                        }}
                    />

                    <div className="absolute inset-0 bg-black/45" />

                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-black/20" />

                    <div className="relative flex h-full min-h-svh flex-col justify-between p-10 text-white">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md">
                                <Calculator className="size-5" />
                            </div>

                            <div>
                                <p className="font-semibold tracking-wide">
                                    {companyName}
                                </p>

                                <p className="text-xs text-white/70">
                                    Financial Management
                                </p>
                            </div>
                        </div>

                        <div className="max-w-lg">
                            <p className="mb-3 text-sm font-medium uppercase tracking-[0.2em] text-white/70">
                                Precision in every number
                            </p>

                            <h2 className="text-4xl font-semibold leading-tight xl:text-5xl">
                                Where every number
                                <br />
                                has its place.
                            </h2>

                            <p className="mt-5 max-w-md text-sm leading-6 text-white/75">
                                Manage your financial records, keep your books
                                organized, and stay on top of your business
                                with confidence.
                            </p>
                        </div>

                        <p className="text-xs text-white/50">
                            © {new Date().getFullYear()} {companyName}
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}