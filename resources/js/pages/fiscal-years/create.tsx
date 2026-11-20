import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, CalendarRange, LoaderCircle } from "lucide-react";
import { useMemo } from "react";
import { Field } from "@/components/ui/field";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "@/components/ui/combobox";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { formatDate } from "@/lib/format";
import { previewPeriods } from "@/lib/periods";

type Props = {
    defaults: { year: number; start_date: string; end_date: string };
    periodsPerYear: number;
    autoGenerate: boolean;
    existingYears: number[];
};

const statusOptions = [
    {
        value: "open",
        label: "Open – accepts postings",
    },
    {
        value: "closed",
        label: "Closed – read only",
    },
];

export default function CreateFiscalYear({
    defaults,
    periodsPerYear,
    autoGenerate,
    existingYears,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        year: String(defaults.year),
        start_date: defaults.start_date,
        end_date: defaults.end_date,
        status: "open",
    });

    const periods = useMemo(
        () => previewPeriods(data.start_date, data.end_date, periodsPerYear),
        [data.start_date, data.end_date, periodsPerYear],
    );

    return (
        <AuthenticatedLayout
            title="New fiscal year"
            description="Accounting periods are generated from the dates you set"
        >
            <Head title="New fiscal year" />

            <div className="max-w-full">
                <Link
                    href="/fiscal-years"
                    className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to fiscal years
                </Link>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post("/fiscal-years");
                    }}
                    className="space-y-4"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Fiscal year</CardTitle>
                            <CardDescription>
                                A year is just a named range of dates that
                                postings must fall inside
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Year"
                                    htmlFor="year"
                                    error={errors.year}
                                    hint={
                                        existingYears.length
                                            ? `Already set up: ${existingYears.join(", ")}`
                                            : undefined
                                    }
                                >
                                    <Input
                                        id="year"
                                        type="number"
                                        inputMode="numeric"
                                        value={data.year}
                                        onChange={(event) =>
                                            setData("year", event.target.value)
                                        }
                                        aria-invalid={!!errors.year}
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Status"
                                    htmlFor="status"
                                    error={errors.status}
                                >
                                    <Combobox
                                        items={statusOptions}
                                        value={
                                            statusOptions.find(
                                                (status) =>
                                                    status.value ===
                                                    data.status,
                                            ) ?? null
                                        }
                                        onValueChange={(status) => {
                                            setData(
                                                "status",
                                                status?.value ?? "open",
                                            );
                                        }}
                                        itemToStringLabel={(status) =>
                                            status ? status.label : ""
                                        }
                                    >
                                        <ComboboxInput
                                            id="status"
                                            placeholder="Select status"
                                            disabled={processing}
                                            className="w-full"
                                        />

                                        <ComboboxContent>
                                            <ComboboxList>
                                                {statusOptions.map((status) => (
                                                    <ComboboxItem
                                                        key={status.value}
                                                        value={status}
                                                    >
                                                        {status.label}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Start date"
                                    htmlFor="start_date"
                                    error={errors.start_date}
                                >
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        onChange={(event) =>
                                            setData(
                                                "start_date",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.start_date}
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="End date"
                                    htmlFor="end_date"
                                    error={errors.end_date}
                                >
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={data.end_date}
                                        onChange={(event) =>
                                            setData(
                                                "end_date",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.end_date}
                                        disabled={processing}
                                    />
                                </Field>
                            </div>

                            {!autoGenerate && (
                                <Alert>
                                    <CalendarRange className="size-4" />
                                    <AlertTitle>
                                        Periods are not generated automatically
                                    </AlertTitle>
                                    <AlertDescription>
                                        You will be able to generate them from
                                        the fiscal year screen afterwards.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Periods</CardTitle>
                                <CardDescription>
                                    {periods.length} period
                                    {periods.length === 1 ? "" : "s"} starting
                                    on the anniversary of the start date
                                </CardDescription>
                            </div>
                            <span className="text-muted-foreground text-xs">
                                {periodsPerYear} per year
                            </span>
                        </CardHeader>
                        <CardContent className="p-0">
                            {periods.length === 0 ? (
                                <p className="text-muted-foreground px-4 py-8 text-center text-sm">
                                    Pick a start date and an end date to see the
                                    periods.
                                </p>
                            ) : (
                                <ul className="bg-border grid grid-cols-1 gap-px sm:grid-cols-2 xl:grid-cols-3">
                                    {periods.map((period) => (
                                        <li
                                            key={period.number}
                                            className="bg-card px-4 py-2.5 text-sm"
                                        >
                                            <span className="font-medium">
                                                {period.number}.{" "}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {period.name}
                                            </span>
                                            <span className="text-muted-foreground/80 mt-0.5 block text-xs">
                                                {formatDate(period.start_date)}{" "}
                                                – {formatDate(period.end_date)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <LoaderCircle className="size-4 animate-spin" />
                            )}
                            {processing ? "Creating…" : "Create fiscal year"}
                        </Button>
                        <Link href="/fiscal-years">
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
