// resources/js/components/dashboard/income-expense-chart.tsx
import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { centsToAmount } from '@/lib/format';

type Point = { month: string; income: number; expense: number };

const config = {
    income: { label: 'Income', color: 'var(--chart-1)' },
    expense: { label: 'Expense', color: 'var(--chart-2)' },
} satisfies ChartConfig;

export function IncomeExpenseChart({ data, currency }: { data: Point[]; currency: string }) {
    const chartData = data.map((point) => ({
        month: point.month,
        income: centsToAmount(point.income),
        expense: centsToAmount(point.expense),
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Income and expense</CardTitle>
            </CardHeader>
            <CardContent>
                <ChartContainer config={config} className="h-64 w-full">
                    <BarChart data={chartData}>
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="month"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                        />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    formatter={(value) =>
                                        new Intl.NumberFormat('en-PH', {
                                            style: 'currency',
                                            currency,
                                        }).format(Number(value))
                                    }
                                />
                            }
                        />
                        <Bar dataKey="income" fill="var(--color-income)" radius={4} />
                        <Bar dataKey="expense" fill="var(--color-expense)" radius={4} />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}