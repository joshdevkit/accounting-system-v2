import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { AccountRow } from "@/types/models"
import { Trash2 } from "lucide-react"


interface DeleteDialogProps {
    account: AccountRow
    onConfirm: (account: AccountRow) => void
}

export function DeleteDialog({
    account,
    onConfirm,
}: DeleteDialogProps) {
    return (
        <AlertDialog>
            <AlertDialogTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive hover:text-destructive"
                    />
                }
            >
                <Trash2 className="size-4" />
                <span className="sr-only">
                    Remove {account.name}
                </span>
            </AlertDialogTrigger>

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Remove account?
                    </AlertDialogTitle>

                    <AlertDialogDescription>
                        Are you sure you want to remove{" "}
                        <span className="font-medium text-foreground">
                            {account.code} · {account.name}
                        </span>
                        ?
                        <span className="mt-2 block">
                            Accounts that already carry journal lines
                            cannot be removed.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel>
                        Cancel
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        onClick={() => onConfirm(account)}
                    >
                        Remove account
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    )
}