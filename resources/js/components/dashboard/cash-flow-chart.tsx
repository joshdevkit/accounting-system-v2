// resources/js/components/dashboard/cash-flow-chart.tsx
import { Area, AreaChart, CartesianGrid, XAxis } from 'recharts';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { centsToAmount } from '@/lib/format';

type Point = { month: string; cash_in: number; cash_out: number };

const config = {
    cash_in: { label: 'Cash in', color: 'var(--chart-1)' },
    cash_out: { label: 'Cash out', color: 'var(--chart-2)' },
} satisfies ChartConfig;

export function CashFlowChart({ data, currency }: { data: Point[]; currency: string }) {
    const chartData = data.map((point) => ({
        month: point.month,
        cash_in: centsToAmount(point.cash_in),
        cash_out: centsToAmount(point.cash_out),
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Cash flow</CardTitle>
            </CardHeader>
            <CardContent>
                <ChartContainer config={config} className="h-64 w-full">
                    <AreaChart data={chartData}>
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
                        <Area
                            dataKey="cash_in"
                            type="monotone"
                            fill="var(--color-cash_in)"
                            fillOpacity={0.2}
                            stroke="var(--color-cash_in)"
                        />
                        <Area
                            dataKey="cash_out"
                            type="monotone"
                            fill="var(--color-cash_out)"
                            fillOpacity={0.2}
                            stroke="var(--color-cash_out)"
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}