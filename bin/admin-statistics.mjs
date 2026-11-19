import { MongoClient } from 'mongodb';
import { randomUUID } from 'node:crypto';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const { orders, menu, from, until } = JSON.parse(input);
const client = new MongoClient(process.env.MONGODB_URI, { serverSelectionTimeoutMS: 4000 });
try {
    await client.connect();
    const collection = client.db(process.env.MONGODB_DATABASE).collection('admin_order_snapshots');
    await collection.createIndex({ expiresAt: 1 }, { expireAfterSeconds: 0 });
    await collection.createIndex({ snapshot: 1, menuId: 1, date: 1 });
    const snapshot = randomUUID();
    const expiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000);
    if (orders.length) {
        await collection.insertMany(orders.map(order => ({
            snapshot, expiresAt, orderId: order.order_id, menuId: order.menu_id,
            date: order.service_date, status: order.status,
            revenueCents: Number(order.revenue_cents)
        })));
    }
    const match = { snapshot, status: { $ne: 'cancelled' } };
    if (menu) match.menuId = menu;
    if (from || until) {
        match.date = {};
        if (from) match.date.$gte = from;
        if (until) match.date.$lte = until;
    }
    const rows = await collection.aggregate([
        { $match: match },
        { $group: {
            _id: '$menuId',
            orders: { $sum: 1 },
            completed: { $sum: { $cond: [{ $eq: ['$status', 'completed'] }, 1, 0] } },
            revenueCents: { $sum: { $cond: [{ $eq: ['$status', 'completed'] }, '$revenueCents', 0] } }
        } }
    ]).toArray();
    process.stdout.write(JSON.stringify(rows));
} catch {
    process.stderr.write('MongoDB indisponible. Vérifiez le service et sa configuration.');
    process.exitCode = 1;
} finally {
    await client.close();
}
