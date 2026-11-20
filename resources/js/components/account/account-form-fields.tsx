import { Field, FieldGrid } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import type { AccountType, ParentOption } from "@/types/models";

export type AccountFormData = {
    code: string;
    name: string;
    account_type_id: string;
    parent_id: string;
    description: string;
    is_control_account: boolean;
    is_active: boolean;
};

type Errors = Partial<Record<keyof AccountFormData, string>>;

export function AccountFormFields({
    data,
    errors,
    processing,
    accountTypes,
    parentOptions,
    onChange,
}: {
    data: AccountFormData;
    errors: Errors;
    processing: boolean;
    accountTypes: Pick<
        AccountType,
        "id" | "name" | "category" | "normal_balance"
    >[];
    parentOptions: ParentOption[];
    onChange: (patch: Partial<AccountFormData>) => void;
}) {
    const selectedType = accountTypes.find(
        (type) => String(type.id) === data.account_type_id,
    );

    return (
        <div className="space-y-4">
            <FieldGrid>
                <Field
                    label="Account code"
                    htmlFor="code"
                    error={errors.code}
                    hint="Unique per company. Codes are also the sort order."
                >
                    <Input
                        id="code"
                        name="code"
                        value={data.code}
                        placeholder="1160"
                        onChange={(event) =>
                            onChange({ code: event.target.value })
                        }
                        aria-invalid={!!errors.code}
                        disabled={processing}
                        autoFocus
                    />
                </Field>

                <Field label="Account name" htmlFor="name" error={errors.name}>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        placeholder="Petty cash"
                        onChange={(event) =>
                            onChange({ name: event.target.value })
                        }
                        aria-invalid={!!errors.name}
                        disabled={processing}
                    />
                </Field>
            </FieldGrid>

            <FieldGrid>
                <Field
                    label="Account type"
                    htmlFor="account_type_id"
                    error={errors.account_type_id}
                    hint={
                        selectedType
                            ? `Normal balance: ${selectedType.normal_balance} (${selectedType.category})`
                            : "Decides the normal balance and where this account reports"
                    }
                >
                    <Combobox
                        items={accountTypes}
                        value={
                            accountTypes.find(
                                (type) =>
                                    String(type.id) === data.account_type_id,
                            ) ?? null
                        }
                        onValueChange={(type) => {
                            onChange({
                                account_type_id: type ? String(type.id) : "",
                            });
                        }}
                        itemToStringLabel={(type) =>
                            type ? `${type.name} · ${type.category}` : ""
                        }
                    >
                        <ComboboxInput
                            id="account_type_id"
                            placeholder="Choose a type…"
                            disabled={processing}
                            aria-invalid={!!errors.account_type_id}
                            className="w-full"
                        />

                        <ComboboxContent>
                            <ComboboxList>
                                {accountTypes.map((type) => (
                                    <ComboboxItem key={type.id} value={type}>
                                        {type.name} · {type.category}
                                    </ComboboxItem>
                                ))}
                            </ComboboxList>
                        </ComboboxContent>
                    </Combobox>
                </Field>

                <Field
                    label="Parent account"
                    htmlFor="parent_id"
                    error={errors.parent_id}
                    hint="Leave empty for a top-level account"
                >
                    <Combobox
                        items={parentOptions}
                        value={
                            parentOptions.find(
                                (option) =>
                                    String(option.id) === data.parent_id,
                            ) ?? null
                        }
                        onValueChange={(option) => {
                            onChange({
                                parent_id: option ? String(option.id) : "",
                            });
                        }}
                        itemToStringLabel={(option) =>
                            option ? option.label : ""
                        }
                    >
                        <ComboboxInput
                            id="parent_id"
                            placeholder="No parent"
                            disabled={processing}
                            className="w-full"
                        />

                        <ComboboxContent>
                            <ComboboxList>
                                {parentOptions.map((option) => (
                                    <ComboboxItem
                                        key={option.id}
                                        value={option}
                                    >
                                        {option.label}
                                    </ComboboxItem>
                                ))}
                            </ComboboxList>
                        </ComboboxContent>
                    </Combobox>
                </Field>
            </FieldGrid>

            <Field
                label="Description"
                htmlFor="description"
                error={errors.description}
            >
                <Textarea
                    id="description"
                    name="description"
                    rows={2}
                    value={data.description}
                    onChange={(event) =>
                        onChange({ description: event.target.value })
                    }
                    aria-invalid={!!errors.description}
                    disabled={processing}
                />
            </Field>

            <div className="flex flex-wrap items-center gap-6 border-t pt-4">
                <Checkbox
                    name="is_control_account"
                    label="Control account"
                    description="Subledger-style account that detail accounts hang off"
                    checked={data.is_control_account}
                    onCheckedChange={(checked) =>
                        onChange({ is_control_account: checked })
                    }
                    disabled={processing}
                />
                <Checkbox
                    name="is_active"
                    label="Active"
                    description="Inactive accounts are hidden from new postings"
                    checked={data.is_active}
                    onCheckedChange={(checked) =>
                        onChange({ is_active: checked })
                    }
                    disabled={processing}
                />
            </div>
        </div>
    );
}
