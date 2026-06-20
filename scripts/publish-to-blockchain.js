import { ethers } from 'ethers';

// Read arguments
const batchId = process.argv[2];
const contractHash = process.argv[3];
const iotHash = process.argv[4];

if (!batchId || !contractHash || !iotHash) {
    console.log(JSON.stringify({
        success: false,
        error: 'Missing arguments: batchId, contractHash, and iotHash are required.'
    }));
    process.exit(1);
}

// Read env variables with hardcoded credentials as fallbacks
const rpcUrl = process.env.BLOCKCHAIN_RPC_URL || ;
const privateKey = process.env.BLOCKCHAIN_PRIVATE_KEY ||;
const contractAddress = process.env.BLOCKCHAIN_CONTRACT_ADDRESS ||;

if (!privateKey) {
    console.log(JSON.stringify({
        success: false,
        error: 'BLOCKCHAIN_PRIVATE_KEY environment variable is not configured.'
    }));
    process.exit(1);
}

async function main() {
    try {
        // Construct the content to be registered as a JSON string
        const contentObj = {
            batch_id: batchId,
            contract_hash: contractHash,
            iot_hash: iotHash
        };
        const contentString = JSON.stringify(contentObj);

        // Setup provider and wallet
        const provider = new ethers.JsonRpcProvider(rpcUrl);
        const wallet = new ethers.Wallet(privateKey, provider);

        // Setup contract interface
        const abi = [
            'function registerDocument(string _content) external'
        ];
        const contract = new ethers.Contract(contractAddress, abi, wallet);

        // Call registerDocument
        const tx = await contract.registerDocument(contentString);

        // Wait for 1 confirmation
        const receipt = await tx.wait(1);

        console.log(JSON.stringify({
            success: true,
            tx_hash: receipt.hash || tx.hash
        }));
        process.exit(0);
    } catch (error) {
        console.log(JSON.stringify({
            success: false,
            error: error.message || String(error)
        }));
        process.exit(1);
    }
}

main();
